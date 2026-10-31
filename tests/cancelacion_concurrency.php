<?php
declare(strict_types=1);
require __DIR__.'/CancelacionPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=new CancelacionPrueba($pdo);$t=$prueba->r->t;$f=$t->fixture;
if(($argv[1]??'')==='worker'){
    $entrada=json_decode(fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
    if(preg_match('/^cu16_[a-f0-9]+_[1-8]$/D',$entrada['nombre']??'')!==1){exit(2);}
    $pdo->prepare("SELECT set_config('application_name',:n,false)")->execute(['n'=>$entrada['nombre']]);
    $pdo->exec("SET lock_timeout='10s'");
    try{
        $valor=match($entrada['accion']){
            'cancelar'=>$prueba->servicio->cancelar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'reprogramar'=>$prueba->r->servicio->reprogramar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'reservar'=>$t->servicio->crear($entrada['datos'],$entrada['actor']),
            'consultar'=>count($t->disponibilidad->consultar($entrada['medico'],$entrada['fecha'],$entrada['especialidad'])['turnos']),
            'desactivar'=>$t->horarios->cambiarEstado($entrada['horario'],false),
        };
        echo json_encode(['resultado'=>'ok','valor'=>$entrada['accion']==='consultar'?$valor:null,'abierta'=>$pdo->inTransaction()]);
    }catch(CitaException $e){echo json_encode(['resultado'=>'rechazado','estado'=>$e->estadoHttp,'abierta'=>$pdo->inTransaction()]);}
    catch(CatalogoValidacionException){echo json_encode(['resultado'=>'rechazado','estado'=>422,'abierta'=>$pdo->inTransaction()]);}
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
    for($caso=1;$caso<=8;++$caso){
        $m=$f->medico();$e=$f->especialidades[array_key_last($f->especialidades)];$h=$t->horario($m);
        $c=$t->servicio->crear($t->datos($m,$p,$e),$a)['id'];$original=$prueba->r->fila($c);
        $cancelar=$prueba->datos($c);$reprogramar=$prueba->r->datos($c,'10:00');$nombre='cu16_'.$f->sufijo.'_'.$caso;
        $entrada=['nombre'=>$nombre,'id'=>$c,'actor'=>$a,'accion'=>'cancelar','datos'=>$cancelar];
        if($caso===6){$martes=$t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>2,'hora_inicio'=>'08:00','hora_fin'=>'12:00']);}
        $pdo->beginTransaction();
        if(in_array($caso,[3,8],true)){$prueba->r->servicio->reprogramar($c,$reprogramar,$a);}
        elseif($caso===6){$t->horarios->cambiarEstado($martes,false);}
        else{$prueba->servicio->cancelar($c,$cancelar,$a);}
        if($caso===2){$entrada['accion']='reprogramar';$entrada['datos']=$reprogramar;}
        elseif($caso===4){$entrada['accion']='consultar';$entrada+=['medico'=>$m,'especialidad'=>$e,'fecha'=>$t->lunes->format('Y-m-d')];}
        elseif($caso===5){$entrada['accion']='reservar';$entrada['datos']=$t->datos($m,$p,$e);}
        elseif($caso===7){$entrada['accion']='desactivar';$entrada['horario']=$h;}
        $lanzar($entrada);
        if(in_array($caso,[4,5],true)){
            $r=$terminar();$ok($pdo->inTransaction(),'carrera '.$caso.': lector termina antes del commit de cancelación');
            $ok($caso===4?$r['resultado']==='ok'&&$r['valor']===7:$r['resultado']==='rechazado'&&$r['estado']===409,'carrera '.$caso.': no se expone turno libre antes del commit');
            $pdo->commit();
        }else{
            $ok($bloqueo($nombre),'carrera '.$caso.': bloqueo real observado en PostgreSQL');
            if($caso===8){$pdo->rollBack();}else{$pdo->commit();}
            $r=$terminar();
        }
        $esperado=in_array($caso,[1,2,3,5],true)?'rechazado':'ok';
        $ok($r['resultado']===$esperado&&!$r['abierta']&&!$pdo->inTransaction(),'carrera '.$caso.': resultado coherente sin transacciones abiertas');
        if(in_array($caso,[1,2,3],true)){$ok($r['estado']===409,'carrera '.$caso.': operación desactualizada rechazada con 409');}
        $actual=$prueba->r->fila($c);$eventos=$prueba->eventos($c);
        $ok($actual['estado']===($caso===3?'programada':'cancelada'),'carrera '.$caso.': estado final correcto');
        $ok(count($eventos)===1&&$eventos[0]['tipo']===($caso===3?'reprogramacion':'cancelacion'),'carrera '.$caso.': solo un evento confirmado, sin historial falso');
        if($caso!==3){$ok($actual['fecha_hora_inicio']===$original['fecha_hora_inicio'],'carrera '.$caso.': cancelación conserva fecha original');}
        unset($actual['estado'],$actual['fecha_hora_inicio'],$original['estado'],$original['fecha_hora_inicio']);
        $ok($actual===$original,'carrera '.$caso.': identidad y demás campos intactos');
        if(in_array($caso,[4,5],true)){
            $ok(count($t->disponibilidad->consultar($m,$t->lunes->format('Y-m-d'),$e)['turnos'])===8,'carrera '.$caso.': CU12 libera turno después del commit');
        }
        if($caso===5){$n=$t->servicio->crear($t->datos($m,$p,$e),$a)['id'];$ok($n!==$c,'CU13 puede reintentar y reservar tras confirmarse cancelación');}
        if($caso===7){$q=$pdo->prepare('SELECT activo FROM horarios_atencion WHERE id=:id');$q->execute(['id'=>$h]);$ok(!$q->fetchColumn(),'CU11 desactiva tras esperar la cancelación confirmada');}
        $q=$pdo->prepare("SELECT count(*) FROM citas a JOIN citas b ON a.id<b.id AND a.medico_id=b.medico_id
            AND a.estado='programada' AND b.estado='programada' AND a.fecha_hora_inicio<b.fecha_hora_inicio+interval '30 minutes'
            AND b.fecha_hora_inicio<a.fecha_hora_inicio+interval '30 minutes' WHERE a.medico_id=:m");$q->execute(['m'=>$m]);
        $ok((int)$q->fetchColumn()===0,'carrera '.$caso.': cero citas programadas superpuestas');
    }
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($proceso)){proc_terminate($proceso);proc_close($proceso);}
    $prueba->limpiar();
}
echo "Aprobadas: $total. Ocho carreras con procesos y conexiones independientes; fixtures propios eliminados.\n";
