<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/HorariosFixture.php';
use App\Config\Database;use App\Models\HorarioAtencion;use App\Repositories\HorarioRepository;use App\Repositories\DisponibilidadRepository;
use App\Services\HorarioService;use App\Services\DisponibilidadService;use App\Services\CatalogoValidacionException;
$pdo=Database::connection();$hr=new HorarioRepository($pdo);$hs=new HorarioService($pdo,$hr);$repo=new DisponibilidadRepository($pdo);$fixture=new HorariosFixture($pdo);
$lunes=(new DateTimeImmutable('next monday',HorarioAtencion::zona()))->modify('+14 days')->setTime(0,0);$ahora=$lunes->modify('-1 day')->setTime(7,0);
$servicio=new DisponibilidadService($pdo,$repo,$hs,static function()use(&$ahora):DateTimeImmutable{return $ahora;});
$total=0;$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);} $total++;echo "OK: $n\n";};
$rechaza=static function(callable $accion,string $campo,string $nombre)use($ok):void{try{$accion();$ok(false,$nombre);}catch(CatalogoValidacionException $e){$ok(isset($e->errores[$campo]),$nombre);}};
$horas=static fn(array $r):array=>array_map(static fn($t)=>(new DateTimeImmutable($t['inicio']))->format('H:i'),$r['turnos']);
$pdo->beginTransaction();
try{
    $m=$fixture->medico();$otro=$fixture->medico();$sinHorario=$fixture->medico();$p=$fixture->paciente();$fecha=$lunes->format('Y-m-d');
    $base=['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'12:00'];$h=$hs->guardar($base);$hs->guardar(array_replace($base,['medico_id'=>$otro]));
    $r=$servicio->consultar($m,$fecha);$ok($horas($r)===['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30'],'ocho turnos exactos sin reservas');
    $ok($r['duracion_minutos']===30 && $r['zona']==='America/La_Paz','duración y zona de clínica');
    $ok($r['turnos'][0]['inicio']===$fecha . 'T08:00:00-04:00' && end($r['turnos'])['fin']===$fecha . 'T12:00:00-04:00','límites exactos sin superar cierre');
    $ok($servicio->consultar($sinHorario,$fecha)['sin_atencion'],'médico sin horarios');
    $ok($servicio->consultar($m,$lunes->modify('+1 day')->format('Y-m-d'))['turnos']===[],'día sin atención');
    $c=$fixture->cita($m,$p,$lunes->setTime(9,0)->format('c'));
    $q=$pdo->prepare("UPDATE citas SET motivo='MOTIVO_PRIVADO_DISPONIBILIDAD' WHERE id=:id");$q->execute(['id'=>$c]);
    $q=$pdo->prepare("UPDATE pacientes SET nombres='PACIENTE_PRIVADO_DISPONIBILIDAD' WHERE id=:id");$q->execute(['id'=>$p]);
    $r=$servicio->consultar($m,$fecha);$libres=$horas($r);
    $ok(count($libres)===7 && !in_array('09:00',$libres,true),'excluye reserva programada');
    $ok(in_array('08:30',$libres,true) && in_array('09:30',$libres,true),'mantiene turnos antes y después sin falso solapamiento');
    $ok(count($servicio->consultar($otro,$fecha)['turnos'])===8,'independencia entre médicos');
    $json=json_encode($r);$ok(!str_contains($json,'PACIENTE_PRIVADO') && !str_contains($json,'MOTIVO_PRIVADO') && !str_contains($json,'paciente_id'),'resultado no divulga pacientes ni motivos');
    $ocupaciones=$repo->ocupaciones($m,$lunes->format('c'),$lunes->modify('+1 day')->format('c'));
    $ok(array_keys($ocupaciones[0])===['fecha_hora_inicio','duracion_minutos'],'repositorio solo devuelve intervalos necesarios');
    $q=$pdo->prepare("UPDATE citas SET estado='cancelada' WHERE id=:id");$q->execute(['id'=>$c]);
    $ok(count($servicio->consultar($m,$fecha)['turnos'])===8,'cancelada libera disponibilidad en consulta siguiente');
    $q=$pdo->prepare("UPDATE citas SET estado='atendida' WHERE id=:id");$q->execute(['id'=>$c]);
    $ok(count($servicio->consultar($m,$fecha)['turnos'])===8,'atendida no es reserva activa según política GiST');
    $q=$pdo->prepare("UPDATE citas SET estado='programada',fecha_hora_inicio=:fecha WHERE id=:id");$q->execute(['id'=>$c,'fecha'=>$lunes->setTime(9,15)->format('c')]);
    $libres=$horas($servicio->consultar($m,$fecha));$ok(count($libres)===6 && !in_array('09:00',$libres,true) && !in_array('09:30',$libres,true),'cita desplazada bloquea ambos candidatos superpuestos');
    $q=$pdo->prepare('DELETE FROM citas WHERE id=:id');$q->execute(['id'=>$c]);
    for($i=0;$i<8;$i++){$fixture->cita($m,$p,$lunes->setTime(8,0)->modify('+' . ($i*30) . ' minutes')->format('c'));}
    $r=$servicio->consultar($m,$fecha);$ok($r['turnos']===[] && !$r['sin_atencion'],'horario completamente ocupado');
    $q=$pdo->prepare('DELETE FROM citas WHERE medico_id=:m AND paciente_id=:p');$q->execute(['m'=>$m,'p'=>$p]);
    $h2=$hs->guardar(array_replace($base,['hora_inicio'=>'14:00','hora_fin'=>'16:00']));
    $libres=$horas($servicio->consultar($m,$fecha));$ok(count($libres)===12 && in_array('14:00',$libres,true) && !in_array('12:00',$libres,true) && !in_array('13:30',$libres,true),'dos tramos respetan descanso');
    $hs->guardar(array_replace($base,['hora_inicio'=>'12:00','hora_fin'=>'13:00']));
    $libres=$horas($servicio->consultar($m,$fecha));$ok(count($libres)===14 && in_array('12:00',$libres,true) && !in_array('13:00',$libres,true),'tramos consecutivos sin duplicar límite');
    $hs->cambiarEstado($h2,false);$ok(!in_array('14:00',$horas($servicio->consultar($m,$fecha)),true),'excluye horario inactivo');
    $ahora=$lunes->setTime(10,15);$libres=$horas($servicio->consultar($m,$fecha));$ok($libres===['10:30','11:00','11:30','12:00','12:30'],'hoy solo ofrece inicios posteriores al momento actual');
    $ahora=$lunes->setTime(10,30);$ok(!in_array('10:30',$horas($servicio->consultar($m,$fecha)),true),'inicio igual al momento actual no se ofrece');
    $ahora=$lunes->setTime(23,0);$ok($servicio->consultar($m,$fecha)['turnos']===[],'jornada de hoy vencida');
    $rechaza(fn()=>$servicio->consultar($m,$lunes->modify('-1 day')->format('Y-m-d')),'fecha','rechaza fecha pasada');
    $ahora=$lunes->modify('-1 day')->setTime(7,0);
    foreach([['medico_id','0'],['medico_id',[]],['medico_id','1 OR 1=1'],['especialidad_id',[]],['especialidad_id','-1'],['fecha',[]],['fecha','2026-02-30'],['fecha','0000-01-01'],['fecha','2026-1-01'],['fecha',"2026-01-01\0"]] as [$campo,$valor]){
        $rechaza(fn()=>$servicio->validarParametros(array_replace(['medico_id'=>$m,'fecha'=>$fecha],[$campo=>$valor])),$campo,'parámetro inválido: ' . $campo);
    }
    $rechaza(fn()=>$servicio->consultar(PHP_INT_MAX,$fecha),'medico_id','médico inexistente');
    $q=$pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id');$q->execute(['id'=>$otro]);$rechaza(fn()=>$servicio->consultar($otro,$fecha),'medico_id','médico inactivo');
    $e=$fixture->especialidades[0];$ajena=$fixture->especialidades[1];
    $ok($servicio->consultar($m,$fecha,$e)['medico']['id']===$m,'especialidad activa vinculada válida');
    $rechaza(fn()=>$servicio->consultar($m,$fecha,$ajena),'medico_id','rechaza médico que no pertenece a especialidad');
    $rechaza(fn()=>$servicio->consultar($m,$fecha,PHP_INT_MAX),'especialidad_id','especialidad inexistente');
    $q=$pdo->prepare('UPDATE especialidades SET activo=false WHERE id=:id');$q->execute(['id'=>$e]);$rechaza(fn()=>$servicio->consultar($m,$fecha,$e),'especialidad_id','especialidad inactiva');
    $ok(!in_array($e,array_column($repo->especialidades(),'id'),true),'selector excluye especialidad inactiva');
    $ok(!in_array($otro,array_column($repo->medicos(null),'id'),true),'selector excluye médico inactivo');
    for($dia=1;$dia<=7;$dia++){$hs->guardar(['medico_id'=>$sinHorario,'dia_semana'=>$dia,'hora_inicio'=>'07:00','hora_fin'=>'08:00']);$r=$servicio->consultar($sinHorario,$lunes->modify('+' . ($dia-1) . ' days')->format('Y-m-d'));$ok($horas($r)===['07:00','07:30'],'ISO día ' . $dia);}
    $noche=$fixture->medico();$hs->guardar(['medico_id'=>$noche,'dia_semana'=>1,'hora_inicio'=>'00:00','hora_fin'=>'01:00']);
    $fixture->cita($noche,$p,$lunes->modify('-15 minutes')->setTimezone(new DateTimeZone('UTC'))->format('c'));
    $ok($horas($servicio->consultar($noche,$fecha))===['00:30'],'incluye cita del día anterior que invade el día local consultado');
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE medico_id=:m');$q->execute(['m'=>$noche]);$antes=$q->fetchColumn();$servicio->consultar($noche,$fecha);$q->execute(['m'=>$noche]);$ok($antes===$q->fetchColumn(),'consultar no crea ni modifica reservas');
}finally{$pdo->rollBack();}
echo "Aprobadas: $total. Datos ficticios revertidos; ninguna cita permanente.\n";
