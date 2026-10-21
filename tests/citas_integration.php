<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/CitasPrueba.php';
use App\Config\Database;use App\Models\Cita;use App\Models\HorarioAtencion;use App\Services\CitaException;
$pdo=Database::connection();$t=new CitasPrueba($pdo);$f=$t->fixture;$s=$t->servicio;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"Falló: $n\n");throw new RuntimeException($n);} $total++;echo "OK: $n\n";};
$rechaza=static function(callable $accion,string $campo,string $nombre,?int $estado=null)use($ok):void{try{$accion();$ok(false,$nombre);}catch(CitaException $e){$ok(isset($e->errores[$campo])&&($estado===null||$e->estadoHttp===$estado),$nombre);}};
$pdo->beginTransaction();
try{
    $admin=$f->usuario('administrador');$recepcion=$f->usuario('recepcionista');$doctor=$f->usuario('medico');$cuentaPaciente=$f->usuario('paciente');
    $m=$f->medico();$otro=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$e2=$f->especialidades[1];$h=$t->horario($m);$t->horario($otro);$base=$t->datos($m,$p,$e);
    $antes=$t->disponibilidad->consultar($m,$base['fecha'])['turnos'];$ok(count($antes)===8,'CU12 antes de reservar muestra ocho turnos');
    $r=$s->crear($base+['motivo'=>'  Revisión ficticia  ','estado'=>'cancelada','duracion_minutos'=>'90','hora_fin'=>'22:00','creada_por_usuario_id'=>$doctor,'id'=>'1','codigo'=>'CM-FALSO'],$admin);
    $ok($r['id']>0&&$r['codigo']===Cita::codigo($r['id']),'cita creada con código derivado de identidad');
    $q=$pdo->prepare('SELECT * FROM citas WHERE id=:id');$q->execute(['id'=>$r['id']]);$fila=$q->fetch();
    $ok((int)$fila['paciente_id']===$p&&(int)$fila['medico_id']===$m&&(int)$fila['especialidad_id']===$e,'persiste paciente, médico y especialidad');
    $ok((new DateTimeImmutable($fila['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona())->format('Y-m-d H:i')===$base['fecha'] . ' 08:00','fecha/hora real en zona clínica');
    $ok($fila['estado']==='programada'&&(int)$fila['duracion_minutos']===30&&$r['fin']===$base['fecha'] . 'T08:30:00-04:00','estado y duración/final no se toman del navegador');
    $ok((int)$fila['creada_por_usuario_id']===$admin&&$fila['motivo']==='Revisión ficticia'&&!empty($fila['creada_en']),'actor confiable, motivo y fecha de creación');
    $ok(count($t->disponibilidad->consultar($m,$base['fecha'])['turnos'])===7,'reserva deja de aparecer en CU12');
    $rechaza(fn()=>$s->crear($base,$admin),'disponibilidad','segundo intento del turno recibe conflicto',409);
    $r2=$s->crear(array_replace($base,['hora_inicio'=>'08:30']),$recepcion);$ok($r2['id']!==$r['id']&&$r2['codigo']!==$r['codigo'],'reservas consecutivas y códigos únicos');
    $simultanea=$s->crear($t->datos($otro,$p,$e2),$admin);$ok($simultanea['id']>0,'mismo paciente con otro médico a la misma hora permitido');
    $rechaza(fn()=>$s->crear(array_replace($base,['paciente_id'=>PHP_INT_MAX,'hora_inicio'=>'09:00']),$admin),'paciente_id','paciente inexistente');
    $q=$pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id');$q->execute(['id'=>$p]);$rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'09:00']),$admin),'paciente_id','paciente inactivo');$pdo->prepare('UPDATE pacientes SET activo=true WHERE id=:id')->execute(['id'=>$p]);
    $rechaza(fn()=>$s->crear(array_replace($base,['medico_id'=>PHP_INT_MAX]),$admin),'medico_id','médico inexistente');
    $pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id')->execute(['id'=>$otro]);$rechaza(fn()=>$s->crear($t->datos($otro,$p,$e2,'09:00'),$admin),'medico_id','médico inactivo');$pdo->prepare('UPDATE medicos SET activo=true WHERE id=:id')->execute(['id'=>$otro]);
    $rechaza(fn()=>$s->crear(array_replace($base,['especialidad_id'=>$e2,'hora_inicio'=>'09:00']),$admin),'especialidad_id','especialidad no asociada');
    $rechaza(fn()=>$s->crear(array_replace($base,['especialidad_id'=>PHP_INT_MAX,'hora_inicio'=>'09:00']),$admin),'especialidad_id','especialidad inexistente');
    $pdo->prepare('UPDATE especialidades SET activo=false WHERE id=:id')->execute(['id'=>$e]);$rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'09:00']),$admin),'especialidad_id','especialidad inactiva');$pdo->prepare('UPDATE especialidades SET activo=true WHERE id=:id')->execute(['id'=>$e]);
    $rechaza(fn()=>$s->crear(array_replace($base,['fecha'=>(new DateTimeImmutable('yesterday',HorarioAtencion::zona()))->format('Y-m-d')]),$admin),'fecha','fecha pasada');
    $rechaza(fn()=>$s->crear(array_replace($base,['fecha'=>(new DateTimeImmutable('now',HorarioAtencion::zona()))->format('Y-m-d'),'hora_inicio'=>'00:00']),$admin),'hora_inicio','hora pasada de hoy');
    $rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'07:30']),$admin),'disponibilidad','fuera del horario');
    $rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'11:45']),$admin),'hora_inicio','inicio no alineado');
    $t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'14:00','hora_fin'=>'16:00']);$rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'13:30']),$admin),'disponibilidad','no reserva en descanso');
    $preview=$t->disponibilidad->consultar($m,$base['fecha'])['turnos'];$ok(count($preview)>0,'consulta informativa anterior existe');
    try{$t->horarios->cambiarEstado($h,false);$ok(false,'CU11 debe proteger la reserva');}
    catch(App\Services\CatalogoValidacionException $e){$ok(isset($e->errores['citas']),'CU11 protege las citas creadas por CU13');}
    $t->horarios->cambiarEstado($t->horarios->guardar(['medico_id'=>$otro,'dia_semana'=>2,'hora_inicio'=>'08:00','hora_fin'=>'10:00']),false);
    $rechaza(fn()=>$s->crear(array_replace($t->datos($otro,$p,$e2),['fecha'=>$t->lunes->modify('+1 day')->format('Y-m-d')]),$admin),'disponibilidad','horario desactivado invalida reserva');
    $h3=$t->horarios->guardar(['medico_id'=>$otro,'dia_semana'=>3,'hora_inicio'=>'08:00','hora_fin'=>'10:00']);$fecha3=$t->lunes->modify('+2 days')->format('Y-m-d');
    $t->disponibilidad->consultar($otro,$fecha3);$t->horarios->guardar(['medico_id'=>$otro,'dia_semana'=>3,'hora_inicio'=>'09:00','hora_fin'=>'10:00'],$h3);
    $rechaza(fn()=>$s->crear(array_replace($t->datos($otro,$p,$e2),['fecha'=>$fecha3]),$admin),'disponibilidad','horario editado se revalida al guardar');
    foreach([$doctor,$cuentaPaciente,PHP_INT_MAX] as $actor){$rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'09:00']),$actor),'permisos','servicio rechaza actor no autorizado',403);}
    $pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:id')->execute(['id'=>$recepcion]);$rechaza(fn()=>$s->crear(array_replace($base,['hora_inicio'=>'09:00']),$recepcion),'permisos','servicio rechaza actor inactivo',403);
    $cantidad=(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn();
    foreach([['paciente_id',[]],['medico_id','0'],['especialidad_id',''],['fecha','2026-02-30'],['hora_inicio','08:00:00'],['motivo',[]],['motivo',str_repeat('x',501)]] as [$campo,$valor]){$rechaza(fn()=>$s->crear(array_replace($base,[$campo=>$valor]),$admin),$campo,'entrada inválida: ' . $campo);}
    $ok((int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()===$cantidad,'fallos no dejan registros parciales');
    $ok($pdo->inTransaction(),'fallo conserva utilizable la transacción externa de pruebas');
    $pdo->prepare("UPDATE citas SET estado='cancelada' WHERE id=:id")->execute(['id'=>$r['id']]);$liberada=$s->crear($base,$admin);$ok($liberada['id']>0,'cancelada no bloquea nueva reserva');
    $ok(Cita::codigo(1234567)==='CM-1234567','código crece sin truncar');
}finally{$pdo->rollBack();}
echo "Aprobadas: $total. Todo revertido, sin citas permanentes.\n";
