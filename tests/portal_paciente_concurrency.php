<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';
if(($argv[1]??'')==='worker'){
    try{
        $d=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);$p=cu15Conexion();
        $q=$p->prepare("SELECT set_config('application_name',:n,false)");$q->execute(['n'=>$d['nombre']]);$p->exec("SET lock_timeout='6s'");$p->exec("SET statement_timeout='8s'");
        $s=new App\Services\PortalPacienteService($p,$d['actor']);echo "READY\n";flush();
        match($d['accion']){
            'vincular'=>(new App\Services\VinculacionPacienteService($p))->vincular($d['paciente'],$d['usuario'],'1',$d['actor']),
            'reservar'=>$s->reservar($d['entrada']),
            'listar'=>$s->listar([]),
            default=>$s->cambiar($d['cita'],$d['accion'],$d['entrada']),
        };echo "200\n";
    }catch(App\Services\CitaException $e){echo $e->estadoHttp."\n";}catch(Throwable){echo "503\n";exit(1);}exit;
}
$pdo=cu15Conexion();$f=null;$procesos=[];$total=0;$antes=cu24Huella($pdo);$token=bin2hex(random_bytes(5));
$iniciar=static function(array $d)use(&$procesos):array{
    $p=proc_open([PHP_BINARY,__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo iniciar proceso CU25.');}$procesos[]=$p;fwrite($pipes[0],json_encode($d,JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);
    if(trim((string)fgets($pipes[1]))!=='READY'){throw new RuntimeException('Proceso CU25 no preparado.');}return ['proceso'=>$p,'salida'=>$pipes[1]];
};
$esperar=static function(array $nombres)use($pdo):void{
    for($i=0;$i<100;$i++){$pdo->query('SELECT pg_stat_clear_snapshot()');$q=$pdo->prepare('SELECT count(*) FROM pg_stat_activity WHERE application_name IN ('.implode(',',array_fill(0,count($nombres),'?')).") AND wait_event_type='Lock'");$q->execute($nombres);if((int)$q->fetchColumn()===count($nombres)){portalAssert(true,'espera real en conexiones independientes');return;}usleep(50000);}throw new RuntimeException('No se observó la espera concurrente CU25.');
};
$fin=static function(array $w)use(&$procesos):int{
    $s=trim(stream_get_contents($w['salida']));fclose($w['salida']);$c=proc_close($w['proceso']);foreach($procesos as $k=>$v){if($v===$w['proceso']){unset($procesos[$k]);}}
    if($c!==0||!ctype_digit($s)){throw new RuntimeException('Proceso CU25 falló.');}return (int)$s;
};
try{
    $f=new PortalPacientePrueba($pdo);$admin=$f->actores['administrador']['id'];$usuarios=[$f->fixture->usuario('paciente'),$f->fixture->usuario('paciente')];
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM pacientes WHERE id=:id FOR UPDATE')->execute(['id'=>$f->sinCuenta]);$workers=[];$nombres=[];
    foreach($usuarios as $i=>$u){$nombre='cu25-'.$token.'-mismo-paciente-'.$i;$nombres[]=$nombre;$workers[]=$iniciar(['nombre'=>$nombre,'accion'=>'vincular','actor'=>$admin,'paciente'=>$f->sinCuenta,'usuario'=>$u]);}
    $esperar($nombres);$pdo->commit();$estados=array_map($fin,$workers);sort($estados);portalAssert($estados===[200,409],'dos cuentas compiten por un paciente sin reasignar');
    $destino=$f->fixture->usuario('paciente');$pacientes=[$f->fixture->paciente(),$f->fixture->paciente()];
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM usuarios WHERE id=:id FOR UPDATE')->execute(['id'=>$destino]);$workers=[];$nombres=[];
    foreach($pacientes as $i=>$p){$nombre='cu25-'.$token.'-misma-cuenta-'.$i;$nombres[]=$nombre;$workers[]=$iniciar(['nombre'=>$nombre,'accion'=>'vincular','actor'=>$admin,'paciente'=>$p,'usuario'=>$destino]);}
    $esperar($nombres);$pdo->commit();$estados=array_map($fin,$workers);sort($estados);portalAssert($estados===[200,409],'dos pacientes compiten por una cuenta sin doble vínculo');
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM medicos WHERE id=:id FOR UPDATE')->execute(['id'=>$f->medico]);$workers=[];$nombres=[];
    foreach(['paciente','otro'] as $i=>$rol){$nombre='cu25-'.$token.'-reserva-'.$i;$nombres[]=$nombre;$workers[]=$iniciar(['nombre'=>$nombre,'accion'=>'reservar','actor'=>$f->actores[$rol]['id'],'entrada'=>$f->entrada()]);}
    $esperar($nombres);$pdo->commit();$estados=array_map($fin,$workers);sort($estados);portalAssert($estados===[200,409],'GiST confirma una sola reserva entre dos titulares');
    $id=$f->cita('09:00');$repro=$f->mutacion($id,'09:30');$cancel=$f->mutacion($id,accion:'cancelar');
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM medicos WHERE id=:id FOR UPDATE')->execute(['id'=>$f->medico]);$workers=[];$nombres=[];
    foreach(['reprogramar'=>$repro,'cancelar'=>$cancel] as $accion=>$e){$nombre='cu25-'.$token.'-'.$accion;$nombres[]=$nombre;$workers[]=$iniciar(['nombre'=>$nombre,'accion'=>$accion,'actor'=>$f->actores['paciente']['id'],'entrada'=>$e,'cita'=>$id]);}
    $esperar($nombres);$pdo->commit();$estados=array_map($fin,$workers);sort($estados);portalAssert($estados===[200,409],'mutaciones concurrentes no aceptan versión obsoleta');portalAssert(count($f->portal()->detalle($id)['eventos']['filas'])===1,'solo un evento atómico');
    // La desactivación confirmada mientras se espera no puede dejar una reserva.
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM medicos WHERE id=:id FOR UPDATE')->execute(['id'=>$f->medico]);$nombre='cu25-'.$token.'-revocacion';
    $w=$iniciar(['nombre'=>$nombre,'accion'=>'reservar','actor'=>$f->actores['paciente']['id'],'entrada'=>$f->entrada('10:00')]);$esperar([$nombre]);
    $pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:id')->execute(['id'=>$f->actores['paciente']['id']]);$pdo->commit();portalAssert($fin($w)===403,'rol y actividad revalidados después de bloqueo');
    $pdo->prepare('UPDATE usuarios SET activo=true WHERE id=:id')->execute(['id'=>$f->actores['paciente']['id']]);
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM pacientes WHERE id=:id FOR UPDATE')->execute(['id'=>$f->paciente]);$nombre='cu25-'.$token.'-lectura';
    $w=$iniciar(['nombre'=>$nombre,'accion'=>'listar','actor'=>$f->actores['paciente']['id']]);$esperar([$nombre]);
    $pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id')->execute(['id'=>$f->paciente]);$pdo->commit();portalAssert($fin($w)===403,'consulta no expone citas tras inactivar vínculo durante espera');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}foreach($procesos as $p){if(is_resource($p)){proc_terminate($p);proc_close($p);}}if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'concurrencia conserva registros anteriores y retira fixtures');
echo "Aprobadas: $total. Concurrencia real de vinculación, reservas, cambios y revocación.\n";
