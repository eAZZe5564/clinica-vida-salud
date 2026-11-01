<?php
declare(strict_types=1);
require __DIR__.'/AsistenciaPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=new AsistenciaPrueba($pdo);$t=$prueba->t;$f=$t->fixture;
if(($argv[1]??'')==='worker'){
    $entrada=json_decode(fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
    if(preg_match('/^cu18_[a-f0-9]+_(?:[1-9]|1[0-6])$/D',$entrada['nombre']??'')!==1){exit(2);}
    $pdo->prepare("SELECT set_config('application_name',:n,false)")->execute(['n'=>$entrada['nombre']]);$pdo->exec("SET lock_timeout='10s'");
    $asistencia=$prueba->servicio(new DateTimeImmutable($entrada['momento']));
    try{
        match($entrada['accion']){
            'llegada','atencion','inasistencia'=>$asistencia->registrar($entrada['id'],$entrada['accion'],$entrada['datos'],$entrada['actor']),
            'cancelar'=>$prueba->previa->c->servicio->cancelar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'reprogramar'=>$prueba->previa->c->r->servicio->reprogramar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'confirmar'=>$prueba->previa->servicio->confirmar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'reservar'=>$t->servicio->crear($entrada['datos'],$entrada['actor']),
            'gist'=>(new App\Repositories\CitaRepository($pdo))->crear($entrada['datos']),
            'desactivar'=>$t->horarios->cambiarEstado($entrada['horario'],false),
        };
        echo json_encode(['resultado'=>'ok','abierta'=>$pdo->inTransaction()]);
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
    $a=$f->usuario('recepcionista');$d=$f->usuario('medico');$p=$f->paciente();$principal=$f->medico($d);$especialidad=$f->especialidades[0];$horario=$t->horario($principal);
    for($caso=1;$caso<=16;++$caso){
        $m=$principal;$e=$especialidad;$h=$horario;
        if($caso===10){$m=$f->medico();$e=$f->especialidades[array_key_last($f->especialidades)];$h=$t->horario($m);}
        $fecha=$t->lunes->modify('+'.(($caso-1)*7).' days');$momento=$fecha->setTime(8,30);$asistencia=$prueba->servicio($momento);
        $reserva=array_replace($t->datos($m,$p,$e),['fecha'=>$fecha->format('Y-m-d')]);
        $c=$t->servicio->crear($reserva,$a)['id'];$original=$prueba->fila($c);
        $reprogramar=$prueba->previa->c->r->datos($c,'10:00',$fecha->format('Y-m-d'));
        $cancelar=$prueba->previa->c->datos($c);$confirmar=$prueba->previa->datos($c);
        if(in_array($caso,[2,5,14],true)){$asistencia->registrar($c,'llegada',$prueba->datos($c),$a);}
        $base=$prueba->datos($c);$nombre='cu18_'.$f->sufijo.'_'.$caso;
        $entrada=['nombre'=>$nombre,'id'=>$c,'actor'=>$a,'accion'=>'llegada','datos'=>$base,'momento'=>$momento->format('c')];
        if($caso===15){$martes=$t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>2,'hora_inicio'=>'08:00','hora_fin'=>'12:00']);}
        $pdo->beginTransaction();
        if(in_array($caso,[2,5,14],true)){$asistencia->registrar($c,'atencion',$base,$d);}
        elseif($caso===3){$asistencia->registrar($c,'inasistencia',$base,$a);}
        elseif($caso===7){$prueba->previa->c->r->servicio->reprogramar($c,$reprogramar,$a);}
        elseif($caso===8){$prueba->previa->servicio->confirmar($c,$confirmar,$a);}
        elseif($caso===11){$prueba->previa->c->servicio->cancelar($c,$cancelar,$a);}
        elseif($caso===15){$t->horarios->cambiarEstado($martes,false);}
        else{$asistencia->registrar($c,'llegada',$base,$a);}
        if($caso===2){$entrada['accion']='atencion';$entrada['actor']=$d;}
        elseif(in_array($caso,[3,4,5],true)){$entrada['accion']='inasistencia';}
        elseif($caso===6){$entrada['accion']='cancelar';$entrada['datos']=$cancelar;}
        elseif($caso===9){$entrada['accion']='reservar';$entrada['datos']=$reserva;}
        elseif($caso===10){$entrada['accion']='desactivar';$entrada['horario']=$h;}
        elseif($caso===12){$entrada['accion']='reprogramar';$entrada['datos']=$reprogramar;}
        elseif($caso===13){$entrada['accion']='confirmar';$entrada['datos']=$confirmar;}
        elseif($caso===14){$entrada['accion']='gist';$entrada['datos']=['medico_id'=>$m,'paciente_id'=>$p,'especialidad_id'=>$e,'inicio'=>$original['fecha_hora_inicio'],'duracion'=>30,'motivo'=>null,'actor'=>$a];}
        $lanzar($entrada);
        if(in_array($caso,[5,9],true)){
            $r=$terminar();$ok($pdo->inTransaction()&&$r['resultado']==='rechazado','carrera '.$caso.': estado ya inelegible u ocupado se rechaza sin esperar commit');$pdo->commit();
        }else{
            $ok($bloqueo($nombre),'carrera '.$caso.': bloqueo real observado en PostgreSQL');
            if($caso===16){$pdo->rollBack();}else{$pdo->commit();}$r=$terminar();
        }
        $ok($r['resultado']===(in_array($caso,[15,16],true)?'ok':'rechazado')&&!$r['abierta']&&!$pdo->inTransaction(),'carrera '.$caso.': resultado coherente y sin transacciones abiertas');
        if($caso===10){$ok($r['estado']===422&&$r['citas'],'CU11 no deja una presencia fuera de cobertura');}
        elseif($caso===14){$ok($r['sqlstate']==='23P01','GiST rechaza reserva incompatible tras finalizar atención');}
        elseif(!in_array($caso,[15,16],true)){$ok($r['estado']===409,'carrera '.$caso.': rechazo compatible con estado o revisión vigente');}
        $estado=match($caso){2,5,14=>'atendida',3=>'inasistencia',7=>'programada',8=>'confirmada',11=>'cancelada',default=>'presente'};
        $tipos=match($caso){2,5,14=>['llegada','atencion'],3=>['inasistencia'],7=>['reprogramacion'],8=>['confirmacion'],11=>['cancelacion'],default=>['llegada']};
        $actual=$prueba->fila($c);$eventos=$prueba->eventos($c);
        $ok($actual['estado']===$estado,'carrera '.$caso.': estado ganador correcto');
        $ok(array_column($eventos,'tipo')===$tipos,'carrera '.$caso.': solo eventos confirmados, sin duplicados o historia falsa');
        if($caso!==7){$ok($actual['fecha_hora_inicio']===$original['fecha_hora_inicio'],'carrera '.$caso.': fecha original intacta');}
        unset($actual['estado'],$actual['fecha_hora_inicio'],$original['estado'],$original['fecha_hora_inicio']);$ok($actual===$original,'carrera '.$caso.': identidad y demás campos conservados');
        $q=$pdo->prepare("SELECT count(*) FROM citas a JOIN citas b ON a.id<b.id AND a.medico_id=b.medico_id
            AND a.estado<>'cancelada' AND b.estado<>'cancelada' AND a.fecha_hora_inicio<b.fecha_hora_inicio+interval '30 minutes'
            AND b.fecha_hora_inicio<a.fecha_hora_inicio+interval '30 minutes' WHERE a.medico_id=:m");$q->execute(['m'=>$m]);
        $ok((int)$q->fetchColumn()===0,'carrera '.$caso.': cero superposiciones activas o históricas');
        $ok(count($t->disponibilidad->consultar($m,$fecha->format('Y-m-d'),$e)['turnos'])===($caso===11?8:7),'carrera '.$caso.': solo cancelada libera intervalo');
    }
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($proceso)){proc_terminate($proceso);proc_close($proceso);}
    $prueba->limpiar();
}
echo "Aprobadas: $total. Dieciséis carreras reales de conexiones; reloj de negocio simulado en fixtures propios eliminados.\n";
