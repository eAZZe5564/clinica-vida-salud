<?php
declare(strict_types=1);
require __DIR__.'/ConfirmacionPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=new ConfirmacionPrueba($pdo);$t=$prueba->c->r->t;$f=$t->fixture;
if(($argv[1]??'')==='worker'){
    $entrada=json_decode(fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
    if(preg_match('/^cu17_[a-f0-9]+_(?:[1-9]|10)$/D',$entrada['nombre']??'')!==1){exit(2);}
    $pdo->prepare("SELECT set_config('application_name',:n,false)")->execute(['n'=>$entrada['nombre']]);
    $pdo->exec("SET lock_timeout='10s'");
    try{
        $valor=match($entrada['accion']){
            'confirmar'=>$prueba->servicio->confirmar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'cancelar'=>$prueba->c->servicio->cancelar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'reprogramar'=>$prueba->c->r->servicio->reprogramar($entrada['id'],$entrada['datos'],$entrada['actor']),
            // Inserción directa deliberada para probar GiST, no sustituye la reserva CU13.
            'gist'=>(new App\Repositories\CitaRepository($pdo))->crear($entrada['datos']),
            'consultar'=>count($t->disponibilidad->consultar($entrada['medico'],$entrada['fecha'],$entrada['especialidad'])['turnos']),
            'desactivar'=>$t->horarios->cambiarEstado($entrada['horario'],false),
        };
        echo json_encode(['resultado'=>'ok','valor'=>$entrada['accion']==='consultar'?$valor:null,'abierta'=>$pdo->inTransaction()]);
    }catch(CitaException $error){echo json_encode(['resultado'=>'rechazado','estado'=>$error->estadoHttp,'abierta'=>$pdo->inTransaction()]);}
    catch(CatalogoValidacionException $error){echo json_encode(['resultado'=>'rechazado','estado'=>422,'citas'=>isset($error->errores['citas']),'abierta'=>$pdo->inTransaction()]);}
    catch(PDOException $error){echo json_encode(['resultado'=>'rechazado','sqlstate'=>(string)$error->getCode(),'abierta'=>$pdo->inTransaction()]);}
    exit;
}
$total=0;$proceso=null;$pipes=[];
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"FALLO: $n\n");throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$lanzar=static function(array $entrada)use(&$proceso,&$pipes):void{
    $proceso=proc_open([PHP_BINARY,__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($proceso)){throw new RuntimeException('No se pudo iniciar el competidor.');}
    fwrite($pipes[0],json_encode($entrada,JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);
};
$bloqueo=static function(string $nombre)use($pdo): bool{
    $q=$pdo->prepare("SELECT count(*) FROM pg_stat_activity WHERE application_name=:n AND wait_event_type='Lock' AND cardinality(pg_blocking_pids(pid))>0");
    for($i=0;$i<70;++$i){$pdo->query('SELECT pg_stat_clear_snapshot()');$q->execute(['n'=>$nombre]);if((int)$q->fetchColumn()>0){return true;}usleep(100000);}return false;
};
$terminar=static function()use(&$proceso,&$pipes):array{
    for($i=0;$i<100;++$i){if(!proc_get_status($proceso)['running']){break;}usleep(100000);}
    if(proc_get_status($proceso)['running']){throw new RuntimeException('El competidor no terminó.');}
    $salida=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proceso);$proceso=null;
    if(trim($error)!==''){throw new RuntimeException('El competidor produjo un error inesperado.');}
    return json_decode($salida,true,512,JSON_THROW_ON_ERROR);
};
try{
    $a=$f->usuario('recepcionista');$p=$f->paciente();
    for($caso=1;$caso<=10;++$caso){
        $m=$f->medico();$e=$f->especialidades[array_key_last($f->especialidades)];$h=$t->horario($m);
        $c=$t->servicio->crear($t->datos($m,$p,$e),$a)['id'];$original=$prueba->c->r->fila($c);
        $confirmar=$prueba->datos($c);$cancelar=$prueba->c->datos($c);$reprogramar=$prueba->c->r->datos($c,'10:00');$nombre='cu17_'.$f->sufijo.'_'.$caso;
        $entrada=['nombre'=>$nombre,'id'=>$c,'actor'=>$a,'accion'=>'confirmar','datos'=>$confirmar];
        if($caso===8){$martes=$t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>2,'hora_inicio'=>'08:00','hora_fin'=>'12:00']);}
        $pdo->beginTransaction();
        if($caso===3){$prueba->c->servicio->cancelar($c,$cancelar,$a);}
        elseif($caso===5){$prueba->c->r->servicio->reprogramar($c,$reprogramar,$a);}
        elseif($caso===8){$t->horarios->cambiarEstado($martes,false);}
        else{$prueba->servicio->confirmar($c,$confirmar,$a);}
        if($caso===2){$entrada['accion']='cancelar';$entrada['datos']=$cancelar;}
        elseif($caso===4){$entrada['accion']='reprogramar';$entrada['datos']=$reprogramar;}
        elseif($caso===6){$entrada['accion']='gist';$entrada['datos']=['medico_id'=>$m,'paciente_id'=>$p,'especialidad_id'=>$e,'inicio'=>$original['fecha_hora_inicio'],'duracion'=>30,'motivo'=>null,'actor'=>$a];}
        elseif($caso===7){$entrada['accion']='desactivar';$entrada['horario']=$h;}
        elseif($caso===10){$entrada['accion']='consultar';$entrada+=['medico'=>$m,'especialidad'=>$e,'fecha'=>$t->lunes->format('Y-m-d')];}
        $lanzar($entrada);
        if($caso===10){
            $r=$terminar();$ok($pdo->inTransaction()&&$r['valor']===7,'CU12 mantiene ocupado el turno antes del commit sin bloquear lectura');$pdo->commit();
        }else{
            $ok($bloqueo($nombre),'carrera '.$caso.': bloqueo real observado en PostgreSQL');
            if($caso===9){$pdo->rollBack();}else{$pdo->commit();}
            $r=$terminar();
        }
        $ok($r['resultado']===(in_array($caso,[8,9,10],true)?'ok':'rechazado')&&!$r['abierta']&&!$pdo->inTransaction(),'carrera '.$caso.': resultado coherente y sin transacciones abiertas');
        if($caso<=5){$ok($r['estado']===409,'carrera '.$caso.': rechaza operación incompatible con 409');}
        if($caso===6){$ok($r['sqlstate']==='23P01','GiST conserva exclusión durante y después de confirmar');}
        if($caso===7){$ok($r['estado']===422&&$r['citas'],'CU11 espera y rechaza desactivar un horario con confirmada');}
        $actual=$prueba->c->r->fila($c);$eventos=$prueba->c->eventos($c);
        $estado=match($caso){3=>'cancelada',5=>'programada',default=>'confirmada'};
        $tipo=match($caso){3=>'cancelacion',5=>'reprogramacion',default=>'confirmacion'};
        $ok($actual['estado']===$estado,'carrera '.$caso.': estado ganador correcto');
        $ok(count($eventos)===1&&$eventos[0]['tipo']===$tipo,'carrera '.$caso.': un evento confirmado, sin historial falso');
        if($caso!==5){$ok($actual['fecha_hora_inicio']===$original['fecha_hora_inicio'],'carrera '.$caso.': horario original conservado');}
        unset($actual['estado'],$actual['fecha_hora_inicio'],$original['estado'],$original['fecha_hora_inicio']);
        $ok($actual===$original,'carrera '.$caso.': identidad y demás campos intactos');
        $ok(count($t->disponibilidad->consultar($m,$t->lunes->format('Y-m-d'),$e)['turnos'])===($caso===3?8:7),'carrera '.$caso.': CU12 solo libera la cancelada');
        $q=$pdo->prepare("SELECT count(*) FROM citas a JOIN citas b ON a.id<b.id AND a.medico_id=b.medico_id
            AND a.estado IN ('programada','confirmada') AND b.estado IN ('programada','confirmada')
            AND a.fecha_hora_inicio<b.fecha_hora_inicio+interval '30 minutes'
            AND b.fecha_hora_inicio<a.fecha_hora_inicio+interval '30 minutes' WHERE a.medico_id=:m");$q->execute(['m'=>$m]);
        $ok((int)$q->fetchColumn()===0,'carrera '.$caso.': cero superposiciones entre reservas activas');
    }
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($proceso)){proc_terminate($proceso);proc_close($proceso);}
    $prueba->limpiar();
}
echo "Aprobadas: $total. Diez carreras con procesos y conexiones independientes; fixtures propios eliminados.\n";
