<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/HorariosFixture.php';
use App\Config\Database;use App\Repositories\HorarioRepository;use App\Services\HorarioService;use App\Services\CatalogoValidacionException;
$pdo=Database::connection();$servicio=new HorarioService($pdo,new HorarioRepository($pdo));
if (($argv[1]??'')==='worker') {
    $id=filter_var($argv[2]??'',FILTER_VALIDATE_INT);$nombre=$argv[3]??'';
    if (!$id || !preg_match('/^horario_concurrencia_[a-f0-9]+$/D',$nombre)) { exit(2); }
    $q=$pdo->prepare("SELECT set_config('application_name',:nombre,false)");$q->execute(['nombre'=>$nombre]);
    $pdo->exec("SET lock_timeout='8s'");
    try{$servicio->guardar(['medico_id'=>$id,'dia_semana'=>1,'hora_inicio'=>'09:00','hora_fin'=>'11:00']);echo 'creado';}
    catch(CatalogoValidacionException $e){echo isset($e->errores['hora_inicio'])?'rechazado':'error_validacion';}
    exit;
}
$fixture=new HorariosFixture($pdo);$proceso=null;$pipes=[];$total=0;
$ok=static function(bool $valor,string $nombre)use(&$total):void{if(!$valor){throw new RuntimeException($nombre);} $total++;echo "OK: $nombre\n";};
try{
    $m=$fixture->medico();$nombre='horario_concurrencia_' . $fixture->sufijo;
    $pdo->beginTransaction();
    $id=$servicio->guardar(['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'12:00']);
    // La primera alta continúa sin commit: el segundo proceso compite por el mismo médico.
    $proceso=proc_open([PHP_BINARY,__FILE__,'worker',(string)$m,$nombre],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if (!is_resource($proceso)) { throw new RuntimeException('No se pudo iniciar el segundo proceso PHP'); }
    fclose($pipes[0]);$bloqueado=false;
    $q=$pdo->prepare("SELECT 1 FROM pg_stat_activity WHERE application_name=:nombre AND wait_event_type='Lock'");
    for($i=0;$i<50;$i++){
        $pdo->query('SELECT pg_stat_clear_snapshot()');$q->execute(['nombre'=>$nombre]);
        if($q->fetchColumn()){$bloqueado=true;break;}usleep(100000);
    }
    $ok($bloqueado,'PostgreSQL muestra al segundo proceso esperando un bloqueo');
    $pdo->commit();$terminado=false;
    for($i=0;$i<50;$i++){if(!proc_get_status($proceso)['running']){$terminado=true;break;}usleep(100000);}
    $ok($terminado,'segundo proceso continúa después del commit');
    $salida=stream_get_contents($pipes[1]);$errores=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);proc_close($proceso);$proceso=null;
    $ok(trim($salida)==='rechazado' && trim($errores)==='','segunda alta superpuesta es rechazada después de leer el primer commit');
    $q=$pdo->prepare('SELECT count(*) FROM horarios_atencion WHERE medico_id=:m AND activo');$q->execute(['m'=>$m]);
    $ok((int)$q->fetchColumn()===1,'solo permanece un horario activo del médico ficticio');
}finally{
    if($pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($proceso)){proc_terminate($proceso);proc_close($proceso);}
    $fixture->limpiar();
}
echo "Aprobadas: $total. Dos procesos PHP y dos sesiones PostgreSQL reales; datos ficticios eliminados.\n";
