<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/CitasPrueba.php';
use App\Config\Database;use App\Services\CitaException;use App\Services\CatalogoValidacionException;
$pdo=Database::connection();$t=new CitasPrueba($pdo);
if(($argv[1]??'')==='worker'){
    $modo=$argv[2]??'';$nombre=$argv[3]??'';$entrada=json_decode(base64_decode($argv[4]??'',true)?:'',true);
    if(!preg_match('/^citas_test_[a-f0-9]+_[1-3]$/D',$nombre)||!is_array($entrada)){exit(2);}
    $q=$pdo->prepare("SELECT set_config('application_name',:n,false)");$q->execute(['n'=>$nombre]);$pdo->exec("SET lock_timeout='8s'");
    try{
        if($modo==='reservar'){$r=$t->servicio->crear($entrada['datos'],(int)$entrada['actor']);}
        elseif($modo==='desactivar'){$t->horarios->cambiarEstado((int)$entrada['horario'],false);$r=[];}
        else{exit(2);}
        echo json_encode(['resultado'=>'creado','abierta'=>$pdo->inTransaction()]);
    }catch(CitaException $e){echo json_encode(['resultado'=>'rechazado','estado'=>$e->estadoHttp,'sqlstate'=>$e->sqlState,'errores'=>array_keys($e->errores),'abierta'=>$pdo->inTransaction()]);}
    catch(CatalogoValidacionException $e){echo json_encode(['resultado'=>'rechazado','errores'=>array_keys($e->errores),'abierta'=>$pdo->inTransaction()]);}
    exit;
}
$f=$t->fixture;$proceso=null;$pipes=[];$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"Falló: $n\n");throw new RuntimeException($n);} $total++;echo "OK: $n\n";};
$lanzar=static function(string $modo,string $nombre,array $entrada)use(&$proceso,&$pipes):void{
    $proceso=proc_open([PHP_BINARY,__FILE__,'worker',$modo,$nombre,base64_encode(json_encode($entrada))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($proceso)){throw new RuntimeException('No se pudo iniciar el proceso competidor');}fclose($pipes[0]);
};
$esperarBloqueo=static function(string $nombre)use($pdo):array{
    $q=$pdo->prepare("SELECT wait_event,pg_blocking_pids(pid) AS bloqueadores FROM pg_stat_activity WHERE application_name=:n AND wait_event_type='Lock'");
    for($i=0;$i<50;$i++){$pdo->query('SELECT pg_stat_clear_snapshot()');$q->execute(['n'=>$nombre]);$r=$q->fetch();if($r){return $r;}usleep(100000);}return [];
};
$terminar=static function()use(&$proceso,&$pipes):array{
    $fin=false;for($i=0;$i<50;$i++){if(!proc_get_status($proceso)['running']){$fin=true;break;}usleep(100000);}
    if(!$fin){throw new RuntimeException('El competidor no terminó');}
    $salida=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proceso);$proceso=null;
    if(trim($error)!==''){throw new RuntimeException('El competidor produjo un error inesperado');}
    return json_decode($salida,true,512,JSON_THROW_ON_ERROR);
};
try{
    $actor=$f->usuario('administrador');$m=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$h=$t->horario($m);$base=$t->datos($m,$p,$e);
    $pdo->beginTransaction();$primera=$t->servicio->crear($base,$actor);
    $nombre='citas_test_' . $f->sufijo . '_1';$lanzar('reservar',$nombre,['datos'=>$base,'actor'=>$actor]);
    $bloqueo=$esperarBloqueo($nombre);$ok($bloqueo!==[],'segunda reserva simultánea espera dentro de PostgreSQL');
    $ok(($bloqueo['wait_event']??'')==='transactionid','espera de transacción tras intentar insertar, no bloqueo exclusivo del médico');
    $pdo->commit();$segunda=$terminar();
    $ok($segunda['resultado']==='rechazado'&&$segunda['estado']===409&&$segunda['sqlstate']==='23P01','GiST produce 23P01 real y se traduce en conflicto comprensible');
    $ok($segunda['abierta']===false&&!$pdo->inTransaction(),'ambas conexiones terminan sin transacciones abiertas');
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE medico_id=:m');$q->execute(['m'=>$m]);$ok((int)$q->fetchColumn()===1,'exactamente una reserva incompatible persiste');
    // CU11 cambia primero: la reserva debe esperar y consultar el horario actualizado.
    $m2=$f->medico();$e2=$f->especialidades[1];$h2=$t->horario($m2);$datos2=$t->datos($m2,$p,$e2);
    $pdo->beginTransaction();$t->horarios->cambiarEstado($h2,false);
    $nombre='citas_test_' . $f->sufijo . '_2';$lanzar('reservar',$nombre,['datos'=>$datos2,'actor'=>$actor]);$ok($esperarBloqueo($nombre)!==[],'reserva espera el cambio simultáneo de CU11');
    $pdo->commit();$r=$terminar();$ok($r['resultado']==='rechazado'&&in_array('disponibilidad',$r['errores'],true),'horario desactivado después de la consulta previa impide reservar');
    $ok($r['abierta']===false,'rechazo tras cambio de horario revierte la transacción');
    $q->execute(['m'=>$m2]);$ok((int)$q->fetchColumn()===0,'cambio concurrente no deja reservas parciales');
    // Reserva primero: CU11 debe esperar y conservar la cita confirmada.
    $m3=$f->medico();$e3=$f->especialidades[2];$h3=$t->horario($m3);
    $pdo->beginTransaction();$t->servicio->crear($t->datos($m3,$p,$e3),$actor);
    $nombre='citas_test_' . $f->sufijo . '_3';$lanzar('desactivar',$nombre,['horario'=>$h3]);$ok($esperarBloqueo($nombre)!==[],'CU11 espera la reserva que mantiene FOR SHARE');
    $pdo->commit();$r=$terminar();$ok($r['resultado']==='rechazado'&&in_array('citas',$r['errores'],true),'CU11 rechaza invalidar la reserva recién confirmada');
    $ok($r['abierta']===false,'CU11 tampoco deja una transacción abierta');
    $q=$pdo->prepare('SELECT activo FROM horarios_atencion WHERE id=:id');$q->execute(['id'=>$h3]);$ok((bool)$q->fetchColumn(),'horario y reserva conservados tras el conflicto');
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}if(is_resource($proceso)){proc_terminate($proceso);proc_close($proceso);}$f->limpiar();
}
echo "Aprobadas: $total. Tres carreras reales con procesos/conexiones independientes; fixtures eliminados.\n";
