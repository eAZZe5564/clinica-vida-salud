<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
require __DIR__ . '/HorariosFixture.php';
use App\Config\Database;
use App\Models\HorarioAtencion;
use App\Repositories\HorarioRepository;
use App\Services\HorarioService;
use App\Services\CatalogoValidacionException;
$pdo=Database::connection();$repo=new HorarioRepository($pdo);$servicio=new HorarioService($pdo,$repo);$fixture=new HorariosFixture($pdo);$total=0;
$ok=static function(bool $valor,string $nombre)use(&$total):void{if(!$valor){throw new RuntimeException($nombre);} $total++;echo "OK: $nombre\n";};
$rechaza=static function(callable $accion,string $campo,string $nombre)use($ok):void{try{$accion();$ok(false,$nombre);}catch(CatalogoValidacionException $e){$ok(isset($e->errores[$campo]),$nombre);}};
$pdo->beginTransaction();
try{
    $m=$fixture->medico();$otro=$fixture->medico();
    $base=['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'12:00'];
    $id=$servicio->guardar($base);$ok($servicio->obtener($id)['activo'],'crear horario válido activo');
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['medico_id'=>PHP_INT_MAX])),'medico_id','rechaza médico inexistente');
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['hora_inicio'=>'11:30','hora_fin'=>'15:30'])),'hora_inicio','rechaza superposición');
    $consecutivo=$servicio->guardar(array_replace($base,['hora_inicio'=>'12:00','hora_fin'=>'16:00']));$ok($consecutivo>0,'permite horarios consecutivos');
    $diferente=$servicio->guardar(array_replace($base,['medico_id'=>$otro]));$ok($diferente>0,'médicos distintos coinciden');
    $ok($repo->listar($m,1,1)['total']===2,'filtro médico y día');
    $ok($repo->listar($m,2,1)['total']===0,'filtro sin resultados');
    $servicio->guardar(array_replace($base,['hora_inicio'=>'08:30']),$id);$ok($servicio->obtener($id)['hora_inicio']==='08:30:00','editar válido');
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['hora_inicio'=>'11:30','hora_fin'=>'13:00']),$id),'hora_inicio','rechaza edición con superposición');
    $ok($servicio->obtener($id)['hora_inicio']==='08:30:00','edición rechazada conserva datos');
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['medico_id'=>$otro]),$id),'medico_id','médico del horario inmutable');
    $servicio->cambiarEstado($id,false);$ok(!$servicio->obtener($id)['activo'],'desactivación lógica');
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['hora_inicio'=>'08:30'])),'hora_inicio','UNIQUE conserva identidad de inicio inactivo');
    $conflicto=$servicio->guardar(array_replace($base,['hora_inicio'=>'09:00','hora_fin'=>'10:00']));
    $rechaza(fn()=>$servicio->cambiarEstado($id,true),'hora_inicio','reactivación no permite superposición');
    $servicio->cambiarEstado($conflicto,false);$servicio->cambiarEstado($id,true);$ok($servicio->obtener($id)['activo'],'reactiva sin conflicto');
    $q=$pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id');$q->execute(['id'=>$otro]);
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['medico_id'=>$otro,'dia_semana'=>2])),'medico_id','rechaza horario nuevo de médico inactivo');
    $servicio->cambiarEstado($diferente,false);$rechaza(fn()=>$servicio->cambiarEstado($diferente,true),'medico_id','rechaza reactivar con médico inactivo');
    $ok($servicio->tramosParaFecha($otro,'2026-10-26')===[],'servicio no ofrece tramos de médico inactivo');
    $ok(count($servicio->tramosParaFecha($m,'2026-10-26'))===2,'lunes ISO para fecha concreta');
    $ok($servicio->tramosParaFecha($m,'2026-10-27')===[],'martes sin tramos');
    $rechaza(fn()=>$servicio->tramosParaFecha($m,'2026-02-30'),'fecha','fecha inválida rechazada');
    // Reserva ficticia futura con fecha elegida según zona de la clínica.
    $fecha=(new DateTimeImmutable('next monday',HorarioAtencion::zona()))->modify('+14 days')->setTime(9,0);
    $paciente=$fixture->paciente();$cita=$fixture->cita($m,$paciente,$fecha->format('c'));
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['hora_inicio'=>'09:30']),$id),'citas','protege cita al reducir horario');
    $rechaza(fn()=>$servicio->guardar(array_replace($base,['dia_semana'=>2]),$id),'citas','protege cita al cambiar día');
    $rechaza(fn()=>$servicio->cambiarEstado($id,false),'citas','protege cita al desactivar');
    $ok($servicio->obtener($id)['activo'] && $servicio->obtener($id)['hora_inicio']==='08:30:00','rollback conserva horario tras proteger cita');
    $q=$pdo->prepare('SELECT estado FROM citas WHERE id=:id');$q->execute(['id'=>$cita]);$ok($q->fetchColumn()==='programada','cita no cancelada ni reprogramada');
    $servicio->guardar($base,$id);$ok($servicio->obtener($id)['hora_inicio']==='08:00:00','edición compatible con cita permitida');
    $q=$pdo->prepare("UPDATE citas SET estado='cancelada' WHERE id=:id");$q->execute(['id'=>$cita]);
    $servicio->cambiarEstado($id,false);$ok(!$servicio->obtener($id)['activo'],'cita cancelada no bloquea cambio');
    $ok(count($servicio->tramosParaFecha($m,$fecha->format('Y-m-d')))===1,'tramos excluyen horarios desactivados');
    // Cita equivalente a lunes local, aunque en UTC ya es martes.
    $nocturno=$servicio->guardar(array_replace($base,['hora_inicio'=>'22:00','hora_fin'=>'23:30']));
    $noche=$fecha->setTime(23,0);$cita2=$fixture->cita($m,$paciente,$noche->setTimezone(new DateTimeZone('UTC'))->format('c'));
    $rechaza(fn()=>$servicio->cambiarEstado($nocturno,false),'citas','protección usa día local y no día UTC');
    $ok((int)$servicio->obtener($nocturno)['id']===$nocturno,'conserva ID');
    for($dia=2;$dia<=7;$dia++){for($h=8;$h<12;$h++){$servicio->guardar(array_replace($base,['dia_semana'=>$dia,'hora_inicio'=>sprintf('%02d:00',$h),'hora_fin'=>sprintf('%02d:30',$h)]));}}
    $resultado=$repo->listar($m,null,1);$ok(count($resultado['filas'])===20 && $resultado['paginas']===2,'paginación limitada');
    try{$servicio->obtener(PHP_INT_MAX);$ok(false,'inexistente');}catch(OutOfBoundsException){$ok(true,'horario inexistente controlado');}
}finally{$pdo->rollBack();}
echo "Aprobadas: $total. Todos los registros se revirtieron; pueden avanzar secuencias.\n";
