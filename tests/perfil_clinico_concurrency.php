<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';

if(($argv[1]??'')==='worker'){
    // Solo IDs ficticios por stdin; no credenciales ni información clínica en comandos.
    try{
        $data=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);$pdo=cu15Conexion();
        $q=$pdo->prepare("SELECT set_config('application_name',:n,false)");$q->execute(['n'=>$data['nombre']]);
        $pdo->exec("SET lock_timeout='6s'");$pdo->exec("SET statement_timeout='8s'");
        $s=new App\Services\PerfilClinicoService($pdo,new App\Repositories\PerfilClinicoRepository($pdo),(int)$data['actor']);
        echo "READY\n";flush();$p=(int)$data['paciente'];
        if($data['operacion']==='perfil'){$s->guardarPerfil($p,['grupo_sanguineo'=>'','fuente_grupo_sanguineo'=>'','observaciones'=>'',
            'estado_alergias'=>'no_registrado','version'=>'0','confirmar'=>'1']);}
        elseif($data['operacion']==='alta'){$s->guardarAlergia($p,['sustancia'=>'CU23_CONCURRENCIA_FICTICIA','reaccion'=>'','observaciones'=>'','confirmar'=>'1']);}
        else{$s->guardarAlergia($p,['sustancia'=>'CU23_CONCURRENCIA_FICTICIA','reaccion'=>'CU23_REACCION_'.$data['nombre'],
            'observaciones'=>'','estado'=>'activa','version'=>(string)$data['version'],'motivo_cambio'=>'CU23_CAMBIO_FICTICIO','confirmar'=>'1'],(int)$data['alergia']);}
        echo json_encode(['estado'=>200],JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(App\Services\PerfilClinicoException $e){echo json_encode(['estado'=>$e->estadoHttp],JSON_THROW_ON_ERROR).PHP_EOL;}
    catch(Throwable){echo json_encode(['estado'=>503],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
    exit;
}
$pdo=cu15Conexion();$prueba=null;$total=0;$procesos=[];
$iniciar=static function(array $d)use(&$procesos):array{
    $p=proc_open([PHP_BINARY,__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo iniciar proceso CU23.');}
    $procesos[]=$p;fwrite($pipes[0],json_encode($d,JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);
    if(trim((string)fgets($pipes[1]))!=='READY'){throw new RuntimeException('Proceso CU23 no preparado.');}
    return ['proceso'=>$p,'salida'=>$pipes[1]];
};
$esperar=static function(array $nombres,int $cantidad)use($pdo):void{
    $sql='SELECT count(*) FROM pg_stat_activity WHERE application_name IN ('.implode(',',array_fill(0,count($nombres),'?')).") AND wait_event_type='Lock'";
    for($i=0;$i<60;$i++){$pdo->query('SELECT pg_stat_clear_snapshot()');$q=$pdo->prepare($sql);$q->execute($nombres);if((int)$q->fetchColumn()===$cantidad){clinicoAssert(true,'procesos independientes realmente esperaron bloqueos');return;}usleep(50000);}
    throw new RuntimeException('No se observaron los bloqueos concurrentes esperados.');
};
$terminar=static function(array $w)use(&$procesos):int{
    $linea=stream_get_contents($w['salida']);fclose($w['salida']);$code=proc_close($w['proceso']);
    foreach($procesos as $k=>$p){if($p===$w['proceso']){unset($procesos[$k]);}}
    $d=json_decode(trim($linea),true,512,JSON_THROW_ON_ERROR);if($code!==0){throw new RuntimeException('Proceso CU23 falló.');}return $d['estado'];
};
try{
    $prueba=new PerfilClinicoPrueba($pdo);$p=$prueba->paciente;$hoy=(new DateTimeImmutable('today',App\Models\HorarioAtencion::zona()))->format('Y-m-d');
    $prueba->cita($p,$prueba->otro,$hoy.'T00:00:00-04:00');$token=bin2hex(random_bytes(5));
    foreach(['perfil','alta','editar'] as $operacion){
        $a=$prueba->servicio()->consultar($p)['alergias'];$id=$a?(int)$a[0]['id']:null;$v=$a?(int)$a[0]['version']:null;
        $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM pacientes WHERE id=:p FOR UPDATE');$q->execute(['p'=>$p]);$workers=[];$nombres=[];
        foreach(['medico','otro_medico'] as $i=>$rol){$nombre='cu23-'.$token.'-'.$operacion.'-'.$i;$nombres[]=$nombre;
            $workers[]=$iniciar(['actor'=>$prueba->actores[$rol]['id'],'paciente'=>$p,'operacion'=>$operacion,'alergia'=>$id,'version'=>$v,'nombre'=>$nombre]);}
        $esperar($nombres,2);$pdo->commit();$estados=array_map($terminar,$workers);sort($estados);
        clinicoAssert($estados===[200,409],'solo un proceso guarda y el otro recibe conflicto');
        $d=$prueba->servicio()->consultar($p);
        if($operacion==='perfil'){clinicoAssert((int)$d['perfil']['version']===1,'creación concurrente deja un solo perfil');}
        elseif($operacion==='alta'){clinicoAssert(count($d['alergias'])===1,'altas concurrentes no duplican alergia activa');}
        else{$actual=$d['alergias'][0];$rev=$prueba->servicio()->consultar($p,(int)$actual['id'])['revisiones'];
            clinicoAssert((int)$actual['version']===2&&count($rev)===2,'edición concurrente no pierde ni duplica revisiones');}
    }
    $nuevo=$prueba->fixture->paciente();$c=$prueba->cita($nuevo,$prueba->medico,$hoy.'T03:00:00-04:00');
    $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM medicos WHERE id=:m FOR UPDATE');$q->execute(['m'=>$prueba->medico]);
    $nombre='cu23-'.$token.'-revocada';$w=$iniciar(['actor'=>$prueba->actores['medico']['id'],'paciente'=>$nuevo,'operacion'=>'perfil','nombre'=>$nombre]);
    $esperar([$nombre],1);
    // Solo el fixture: simula una revocación mientras la operación espera, sin tocar reglas CU16.
    $q=$pdo->prepare("UPDATE citas SET estado='cancelada' WHERE id=:id");$q->execute(['id'=>$c]);$pdo->commit();
    clinicoAssert($terminar($w)===404,'permiso revocado mientras espera se revalida antes de escribir');
    $q=$pdo->prepare('SELECT count(*) FROM perfiles_clinicos WHERE paciente_id=:p');$q->execute(['p'=>$nuevo]);clinicoAssert((int)$q->fetchColumn()===0,'revocación no deja perfil parcial');
    $base=$prueba->huella();$pdo->beginTransaction();$q=$pdo->prepare('UPDATE perfiles_clinicos SET observaciones=:o WHERE paciente_id=:p');$q->execute(['o'=>'CU23_EXTERNO_FICTICIO','p'=>$p]);
    $s=$prueba->servicio(pdo:new PerfilClinicoFalloPDO($pdo));
    clinicoRechaza(fn()=>$s->guardarAlergia($p,$prueba->alergia(['sustancia'=>'CU23_ROLLBACK_FICTICIO'])),503,'error anidado revierte únicamente su savepoint');
    $q=$pdo->prepare('SELECT observaciones FROM perfiles_clinicos WHERE paciente_id=:p');$q->execute(['p'=>$p]);
    clinicoAssert($pdo->inTransaction()&&$q->fetchColumn()==='CU23_EXTERNO_FICTICIO','operación anidada conserva la transacción del llamador');
    $pdo->rollBack();clinicoAssert($base===$prueba->huella(),'rollback exterior restaura las doce tablas');
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}
    foreach($procesos as $p){if(is_resource($p)){proc_terminate($p);proc_close($p);}}
    if($prueba){$prueba->limpiar();}
}
echo "Aprobadas: $total. Procesos PDO independientes, espera real, revocación y savepoints; fixtures retirados.\n";
