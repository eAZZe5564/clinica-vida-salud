<?php
declare(strict_types=1);
require __DIR__ . '/ReprogramacionPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=new ReprogramacionPrueba($pdo);$t=$prueba->t;$f=$t->fixture;
if(($argv[1]??'')==='worker'){
    $entrada=json_decode(fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
    if(preg_match('/^cu15_[a-f0-9]+_[1-6]$/D',$entrada['nombre']??'')!==1){exit(2);}
    $pdo->prepare("SELECT set_config('application_name',:n,false)")->execute(['n'=>$entrada['nombre']]);
    $pdo->exec("SET lock_timeout='10s'");
    try{
        match($entrada['accion']){
            'reservar'=>$t->servicio->crear($entrada['datos'],$entrada['actor']),
            'reprogramar'=>$prueba->servicio->reprogramar($entrada['id'],$entrada['datos'],$entrada['actor']),
            'desactivar'=>$t->horarios->cambiarEstado($entrada['horario'],false),
        };
        echo json_encode(['resultado'=>'ok','abierta'=>$pdo->inTransaction()]);
    }catch(CitaException $e){echo json_encode(['resultado'=>'rechazado','estado'=>$e->estadoHttp,'sqlstate'=>$e->sqlState,'errores'=>array_keys($e->errores),'abierta'=>$pdo->inTransaction()]);}
    catch(CatalogoValidacionException $e){echo json_encode(['resultado'=>'rechazado','errores'=>array_keys($e->errores),'abierta'=>$pdo->inTransaction()]);}
    exit;
}
$total=0;$proceso=null;$pipes=[];
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$lanzar=static function(array $entrada)use(&$proceso,&$pipes):void{
    $proceso=proc_open([PHP_BINARY,__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($proceso)){throw new RuntimeException('No se pudo iniciar el competidor.');}
    fwrite($pipes[0],json_encode($entrada,JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);
};
$bloqueo=static function(string $nombre)use($pdo): array{
    $q=$pdo->prepare("SELECT wait_event,pg_blocking_pids(pid) AS bloqueadores FROM pg_stat_activity WHERE application_name=:n AND wait_event_type='Lock'");
    for($i=0;$i<70;++$i){$pdo->query('SELECT pg_stat_clear_snapshot()');$q->execute(['n'=>$nombre]);if($fila=$q->fetch()){return $fila;}usleep(100000);}return [];
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
    for($caso=1;$caso<=6;++$caso){
        $m=$f->medico();$e=$f->especialidades[array_key_last($f->especialidades)];$h=$t->horario($m);
        $c=$t->servicio->crear($t->datos($m,$p,$e),$a)['id'];$original=$prueba->fila($c);
        $fecha=$t->lunes->format('Y-m-d');$nombre='cu15_'.$f->sufijo.'_'.$caso;
        $entrada=['nombre'=>$nombre,'actor'=>$a,'id'=>$c];
        if($caso>=4){
            $fecha=$t->lunes->modify('+1 day')->format('Y-m-d');
            $h=$t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>2,'hora_inicio'=>'08:00','hora_fin'=>'12:00']);
        }
        $datos=$prueba->datos($c,'10:00',$fecha);
        $pdo->beginTransaction();
        if($caso===1){$prueba->servicio->reprogramar($c,$datos,$a);$entrada+=['accion'=>'reservar','datos'=>array_replace($t->datos($m,$p,$e,'10:00'),['fecha'=>$fecha])];}
        elseif($caso===2){$t->servicio->crear($t->datos($m,$p,$e,'10:00'),$a);$entrada+=['accion'=>'reprogramar','datos'=>$datos];}
        elseif($caso===3){$prueba->servicio->reprogramar($c,$datos,$a);$entrada+=['accion'=>'reprogramar','datos'=>array_replace($datos,['hora_inicio'=>'10:30'])];}
        elseif($caso===4){$t->horarios->cambiarEstado($h,false);$entrada+=['accion'=>'reprogramar','datos'=>$datos];}
        elseif($caso===5){$prueba->servicio->reprogramar($c,$datos,$a);$entrada+=['accion'=>'desactivar','horario'=>$h];}
        else{$t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>2,'hora_inicio'=>'11:00','hora_fin'=>'12:00'],$h);$entrada+=['accion'=>'reprogramar','datos'=>$datos];}
        $lanzar($entrada);$espera=$bloqueo($nombre);
        $ok($espera!==[],'carrera '.$caso.': espera de bloqueo real observada en PostgreSQL');
        if(in_array($caso,[1,2],true)){$ok(($espera['wait_event']??'')==='transactionid','GiST arbitra reserva CU13 contra reprogramación CU15');}
        $pdo->commit();$r=$terminar();
        $ok($r['resultado']==='rechazado'&&!$r['abierta']&&!$pdo->inTransaction(),'carrera '.$caso.': rechazo comprensible sin transacción abierta');
        if(in_array($caso,[1,2],true)){$ok($r['sqlstate']==='23P01'&&$r['estado']===409,'23P01 real traducido a conflicto 409');}
        if(in_array($caso,[2,4,6],true)){$ok($prueba->fila($c)===$original,'carrera '.$caso.': cita perdedora conserva íntegramente horario original');}
        else{$ok(new DateTimeImmutable($prueba->fila($c)['fecha_hora_inicio'])==new DateTimeImmutable($fecha.' 10:00',App\Models\HorarioAtencion::zona()),'carrera '.$caso.': horario ganador conservado');}
        $q=$pdo->prepare('SELECT count(*) FROM citas_historial WHERE cita_id=:id');$q->execute(['id'=>$c]);
        $ok((int)$q->fetchColumn()===(in_array($caso,[1,3,5],true)?1:0),'carrera '.$caso.': historial solo de operaciones confirmadas');
        $q=$pdo->prepare("SELECT count(*) FROM citas a JOIN citas b ON a.id<b.id AND a.medico_id=b.medico_id
            AND a.estado='programada' AND b.estado='programada' AND a.fecha_hora_inicio<b.fecha_hora_inicio+interval '30 minutes'
            AND b.fecha_hora_inicio<a.fecha_hora_inicio+interval '30 minutes' WHERE a.medico_id=:m");$q->execute(['m'=>$m]);
        $ok((int)$q->fetchColumn()===0,'carrera '.$caso.': cero citas superpuestas');
    }
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($proceso)){proc_terminate($proceso);proc_close($proceso);}
    $prueba->limpiar();
}
echo "Aprobadas: $total. Seis carreras con procesos y conexiones independientes; fixtures propios eliminados.\n";
