<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';
if(($argv[1]??'')==='worker'){
    try{
        $d=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);$p=cu15Conexion();
        $q=$p->prepare("SELECT set_config('application_name',:n,false)");$q->execute(['n'=>$d['nombre']]);$p->exec("SET lock_timeout='6s'");$p->exec("SET statement_timeout='8s'");
        $s=new App\Services\RegistroClinicoService($p,new App\Repositories\RegistroClinicoRepository($p),(int)$d['actor']);
        echo "READY\n";flush();$s->guardar((int)$d['paciente'],$d['tipo'],$d['entrada'],$d['id']);echo "200\n";
    }catch(App\Services\PerfilClinicoException $e){echo $e->estadoHttp."\n";}
    catch(Throwable){echo "503\n";exit(1);}exit;
}
$pdo=cu15Conexion();$f=null;$procesos=[];$total=0;
$iniciar=static function(array $d)use(&$procesos):array{
    $p=proc_open([PHP_BINARY,__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo iniciar proceso CU24.');}$procesos[]=$p;
    fwrite($pipes[0],json_encode($d,JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);if(trim((string)fgets($pipes[1]))!=='READY'){throw new RuntimeException('Proceso CU24 no preparado.');}
    return ['proceso'=>$p,'salida'=>$pipes[1]];
};
$esperar=static function(array $nombres,int $cantidad)use($pdo):void{
    for($i=0;$i<80;$i++){
        $pdo->query('SELECT pg_stat_clear_snapshot()');$q=$pdo->prepare('SELECT count(*) FROM pg_stat_activity WHERE application_name IN ('.implode(',',array_fill(0,count($nombres),'?')).") AND wait_event_type='Lock'");$q->execute($nombres);
        if((int)$q->fetchColumn()===$cantidad){clinicoAssert(true,'procesos PDO independientes esperaron bloqueos reales');return;}usleep(50000);
    }throw new RuntimeException('No se observó la espera concurrente CU24.');
};
$fin=static function(array $w)use(&$procesos):int{
    $s=trim(stream_get_contents($w['salida']));fclose($w['salida']);$c=proc_close($w['proceso']);foreach($procesos as $k=>$v){if($v===$w['proceso']){unset($procesos[$k]);}}
    if($c!==0||!ctype_digit($s)){throw new RuntimeException('Proceso CU24 falló.');}return (int)$s;
};
try{
    $f=new PerfilClinicoPrueba($pdo);$p=$f->paciente;$s=cu24Servicio($f);$hoy=new DateTimeImmutable('today',App\Models\HorarioAtencion::zona());$f->cita($p,$f->otro,$hoy->format('c'));$token=bin2hex(random_bytes(5));
    foreach(['antecedentes','laboratorios'] as $tipo){
        $id=$s->guardar($p,$tipo,cu24Entrada($tipo));$campo=$tipo==='antecedentes'?'descripcion':'resultado_texto';$entrada=cu24Cambio($f,$tipo,$id,[$campo=>'CU24_CONCURRENTE_FICTICIO']);
        $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM pacientes WHERE id=:p FOR UPDATE');$q->execute(['p'=>$p]);$workers=[];$nombres=[];
        foreach(['medico','otro_medico'] as $i=>$rol){$nombre='cu24-'.$token.'-'.$tipo.'-'.$i;$nombres[]=$nombre;$workers[]=$iniciar(['nombre'=>$nombre,'actor'=>$f->actores[$rol]['id'],'paciente'=>$p,'tipo'=>$tipo,'entrada'=>$entrada,'id'=>$id]);}
        $esperar($nombres,2);$pdo->commit();$estados=array_map($fin,$workers);sort($estados);clinicoAssert($estados===[200,409],'una corrección se confirma y otra recibe conflicto');
        $d=$s->consultar($p,$tipo,$id);clinicoAssert((int)$d['registro']['version']===2&&count($d['revisiones'])===2,'sin sobrescrituras ni versiones históricas perdidas');
        clinicoAssert($d['revisiones'][1]['version']===1&&$d['revisiones'][0]['version']===2,'instantáneas anterior y actual únicas');
    }
    $np=$f->fixture->paciente();$cita=$f->cita($np,$f->medico,$hoy->setTime(4,0)->format('c'));
    $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM medicos WHERE id=:m FOR UPDATE');$q->execute(['m'=>$f->medico]);$nombre='cu24-'.$token.'-revocada';
    $w=$iniciar(['nombre'=>$nombre,'actor'=>$f->actores['medico']['id'],'paciente'=>$np,'tipo'=>'antecedentes','entrada'=>cu24Entrada('antecedentes'),'id'=>null]);$esperar([$nombre],1);
    $q=$pdo->prepare("UPDATE citas SET estado='cancelada' WHERE id=:id");$q->execute(['id'=>$cita]);$pdo->commit();clinicoAssert($fin($w)===404,'permiso revocado durante espera no permite guardar');
    $q=$pdo->prepare('SELECT count(*) FROM antecedentes_clinicos WHERE paciente_id=:p');$q->execute(['p'=>$np]);clinicoAssert((int)$q->fetchColumn()===0,'revocación no deja antecedente parcial');
    // CU23 y CU24 comparten el mismo bloqueo de paciente incluso entre médicos diferentes.
    $pdo->beginTransaction();$f->servicio()->guardarPerfil($p,$f->perfil());$nombre='cu24-'.$token.'-con-cu23';
    $w=$iniciar(['nombre'=>$nombre,'actor'=>$f->actores['otro_medico']['id'],'paciente'=>$p,'tipo'=>'laboratorios','entrada'=>cu24Entrada('laboratorios'),'id'=>null]);$esperar([$nombre],1);$pdo->commit();
    clinicoAssert($fin($w)===200,'CU24 continúa después de confirmar CU23 sin interbloqueo');clinicoAssert($f->servicio()->consultar($p)['perfil']!==null,'se conserva la operación previa de CU23');
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}foreach($procesos as $p){if(is_resource($p)){proc_terminate($p);proc_close($p);}}cu24Limpiar($f);
}
echo "Aprobadas: $total. Concurrencia real entre médicos, revocación y coordinación CU23/CU24.\n";
