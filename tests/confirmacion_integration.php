<?php
declare(strict_types=1);
require __DIR__.'/ConfirmacionPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=new ConfirmacionPrueba($pdo);$t=$prueba->c->r->t;$f=$t->fixture;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"FALLO: $n\n");throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $accion,int $estado,string $n)use($ok):void{
    try{$accion();$ok(false,$n);}catch(CitaException $e){$ok($e->estadoHttp===$estado,$n);}
};
$pdo->beginTransaction();
try{
    $a=$f->usuario('administrador');$r=$f->usuario('recepcionista');$u=$f->usuario('medico');$pa=$f->usuario('paciente');
    $m=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$h=$t->horario($m);
    $crear=fn(string $hora)=>$t->servicio->crear($t->datos($m,$p,$e,$hora),$a)['id'];
    $c=$crear('08:00');$antes=$prueba->c->r->fila($c);$datos=$prueba->datos($c);
    $desde=new DateTimeImmutable($pdo->query('SELECT clock_timestamp()')->fetchColumn());
    $resultado=$prueba->servicio->confirmar($c,$datos,$a);$despues=$prueba->c->r->fila($c);$eventos=$prueba->c->eventos($c);$evento=$eventos[0];
    $ok($resultado['estado']==='confirmada'&&$despues['estado']==='confirmada','confirmación válida cambia a confirmada');
    $ok($resultado['codigo']===App\Models\Cita::codigo($c),'código conservado');
    unset($antes['estado'],$despues['estado']);$ok($antes===$despues,'todos los demás campos e identidad intactos');
    $ok(count($eventos)===1&&$evento['tipo']==='confirmacion','un evento de confirmación');
    $ok((int)$evento['usuario_id']===$a&&$evento['estado_anterior']==='programada'&&$evento['estado_nuevo']==='confirmada','actor y transición correctos');
    $ok($evento['inicio_anterior']===$evento['inicio_nuevo']&&$evento['inicio_nuevo']===$antes['fecha_hora_inicio'],'historial conserva fecha y hora');
    $ok($evento['motivo_cancelacion']===null,'confirmación sin motivo de cancelación');
    $momento=new DateTimeImmutable($evento['cambiado_en']);$hasta=new DateTimeImmutable($pdo->query('SELECT clock_timestamp()')->fetchColumn());
    $ok($momento>=$desde&&$momento<=$hasta,'momento real de operación TIMESTAMPTZ');
    $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($pdo),new App\Repositories\HorarioRepository($pdo),['id'=>$a,'rol'=>'administrador']);
    $ok($consulta->detalle($c)['estado']==='confirmada','CU14 consulta estado confirmado');
    $ok($consulta->listar(['codigo'=>$resultado['codigo'],'estado'=>'confirmada'])['total']===1,'CU14 filtra confirmadas');
    $ok(count($t->disponibilidad->consultar($m,$t->lunes->format('Y-m-d'),$e)['turnos'])===7,'CU12 conserva turno ocupado');
    $rechazar(fn()=>$crear('08:00'),409,'CU13 no reserva turno confirmado');
    foreach(['programada','confirmada'] as $estado){
        $pdo->exec('SAVEPOINT choque_gist');$codigo=null;
        try{$q=$pdo->prepare('INSERT INTO citas(paciente_id,medico_id,especialidad_id,fecha_hora_inicio,estado) VALUES (:p,:m,:e,:inicio,:estado)');$q->execute(['p'=>$p,'m'=>$m,'e'=>$e,'inicio'=>$antes['fecha_hora_inicio'],'estado'=>$estado]);}
        catch(PDOException $error){$codigo=(string)$error->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT choque_gist');$pdo->exec('RELEASE SAVEPOINT choque_gist');}
        $ok($codigo==='23P01','GiST rechaza reserva directa '.$estado.' sobre confirmada');
    }
    $rechazar(fn()=>$prueba->c->r->servicio->obtener($c),409,'CU15 conserva política solo programada');
    $rechazar(fn()=>$prueba->c->servicio->obtener($c),409,'CU16 conserva política solo programada');
    $rechazar(fn()=>$prueba->servicio->confirmar($c,$datos,$r),409,'segunda confirmación rechazada');
    $ok(count($prueba->c->eventos($c))===1,'segunda solicitud no duplica historial');
    $rechazar(fn()=>$prueba->servicio->confirmar(PHP_INT_MAX,$datos,$a),404,'cita inexistente');
    foreach(['cancelada','atendida'] as $i=>$estado){
        $id=$crear($i===0?'09:00':'09:30');$base=$prueba->datos($id);
        $pdo->prepare('UPDATE citas SET estado=:estado WHERE id=:id')->execute(['estado'=>$estado,'id'=>$id]);
        $rechazar(fn()=>$prueba->servicio->confirmar($id,$base,$a),409,'no confirma estado '.$estado);
        $ok($prueba->c->eventos($id)===[],'estado rechazado sin historial falso');
    }
    $c2=$crear('10:00');$base=$prueba->datos($c2);$original=$prueba->c->r->fila($c2);
    foreach([$u,$pa,PHP_INT_MAX] as $actor){$rechazar(fn()=>$prueba->servicio->confirmar($c2,$base,$actor),403,'servicio rechaza actor no autorizado');}
    $pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:id')->execute(['id'=>$r]);
    $rechazar(fn()=>$prueba->servicio->confirmar($c2,$base,$r),403,'actor inactivo rechazado');
    $pdo->prepare('UPDATE usuarios SET activo=true WHERE id=:id')->execute(['id'=>$r]);
    foreach(['',[]] as $accion){$rechazar(fn()=>$prueba->servicio->confirmar($c2,array_replace($base,['accion'=>$accion]),$a),422,'confirmación explícita requerida');}
    foreach(['',[],str_repeat('x',81)] as $token){$rechazar(fn()=>$prueba->servicio->confirmar($c2,array_replace($base,['original'=>$token]),$a),409,'referencia inválida rechazada');}
    foreach(["UPDATE citas SET estado='confirmada'",'INSERT INTO citas_historial'] as $fallar){
        $proxy=new Cu15FalloPDO($pdo,$fallar);$servicio=new App\Services\ConfirmacionService($proxy,new App\Repositories\CitaRepository($proxy));
        $rechazar(fn()=>$servicio->confirmar($c2,$base,$a),503,'fallo SQL controlado: '.$fallar);
        $ok($prueba->c->r->fila($c2)===$original&&$prueba->c->eventos($c2)===[],'rollback de estado e historial ante fallo');
    }
    $prueba->c->r->servicio->reprogramar($c2,$prueba->c->r->datos($c2,'11:00'),$a);
    $rechazar(fn()=>$prueba->servicio->confirmar($c2,$base,$a),409,'formulario viejo no confirma horario reprogramado');
    $prueba->servicio->confirmar($c2,$prueba->datos($c2),$r);
    $ok(count($prueba->c->eventos($c2))===2&&$prueba->c->eventos($c2)[1]['tipo']==='confirmacion','recepcionista confirma con historial de CU15 conservado');
    try{$t->horarios->cambiarEstado($h,false);$ok(false,'CU11 protege confirmadas');}catch(CatalogoValidacionException $error){$ok(isset($error->errores['citas']),'CU11 no desactiva horario con confirmadas');}
    try{$t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'08:30','hora_fin'=>'12:00'],$h);$ok(false,'CU11 no recorta confirmadas');}catch(CatalogoValidacionException $error){$ok(isset($error->errores['citas']),'CU11 no recorta horario dejando confirmada fuera de cobertura');}
    $c3=$crear('10:30');$prueba->c->servicio->cancelar($c3,$prueba->c->datos($c3),$a);
    $nueva=$crear('10:30');$ok($nueva!==$c3,'canceladas siguen liberando turnos para CU13');
    $ok(count($prueba->c->eventos($c3))===1&&$prueba->c->eventos($c3)[0]['tipo']==='cancelacion','CU16 sigue registrando cancelaciones');
    $insertar=$pdo->prepare('INSERT INTO citas_historial(cita_id,tipo,inicio_anterior,inicio_nuevo,estado_anterior,estado_nuevo,usuario_id,motivo_cancelacion) VALUES (:c,:tipo,:anterior,:nuevo,:antes,:despues,:u,:motivo)');
    $eventoBase=['c'=>$c,'tipo'=>'confirmacion','anterior'=>$antes['fecha_hora_inicio'],'nuevo'=>$antes['fecha_hora_inicio'],'antes'=>'programada','despues'=>'confirmada','u'=>$a,'motivo'=>null];
    foreach([['tipo'=>'otro'],['despues'=>'programada'],['antes'=>'confirmada'],['motivo'=>'no permitido'],['nuevo'=>$t->lunes->setTime(9,0)->format('c')]] as $cambio){
        $pdo->exec('SAVEPOINT evento_invalido');$codigo=null;
        try{$insertar->execute(array_replace($eventoBase,$cambio));}catch(PDOException $error){$codigo=(string)$error->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT evento_invalido');$pdo->exec('RELEASE SAVEPOINT evento_invalido');}
        $ok($codigo==='23514','CHECK de 007 rechaza evento de confirmación inválido');
    }
    $c4=$crear('11:30');$datos=$prueba->datos($c4);$original=$prueba->c->r->fila($c4);
    $pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id')->execute(['id'=>$m]);
    $pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id')->execute(['id'=>$p]);
    $prueba->servicio->confirmar($c4,$datos,$a);$actual=$prueba->c->r->fila($c4);
    $ok($actual['estado']==='confirmada'&&array_diff_assoc($actual,$original)===['estado'=>'confirmada'],'confirmar reserva previa no exige reactivar catálogos ni altera otros datos');
    $insercionRechazada=static function(string $estado,string $inicio)use($pdo,$p,$m,$e,$ok):void{
        $pdo->exec('SAVEPOINT trigger_reserva');$codigo=null;
        try{$q=$pdo->prepare('INSERT INTO citas(paciente_id,medico_id,especialidad_id,fecha_hora_inicio,estado) VALUES (:p,:m,:e,:inicio,:estado)');$q->execute(['p'=>$p,'m'=>$m,'e'=>$e,'inicio'=>$inicio,'estado'=>$estado]);}
        catch(PDOException $error){$codigo=(string)$error->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT trigger_reserva');$pdo->exec('RELEASE SAVEPOINT trigger_reserva');}
        $ok($codigo==='23514','trigger 007 conserva reglas críticas al reservar '.$estado);
    };
    $insercionRechazada('confirmada',$t->lunes->setTime(10,0)->format('c'));
    $pdo->prepare('UPDATE medicos SET activo=true WHERE id=:id')->execute(['id'=>$m]);
    $insercionRechazada('confirmada',$t->lunes->setTime(10,0)->format('c'));
    $pdo->prepare('UPDATE pacientes SET activo=true WHERE id=:id')->execute(['id'=>$p]);
    foreach(['programada','confirmada'] as $estado){$insercionRechazada($estado,'2000-01-01T08:00:00-04:00');}
    $ok($pdo->inTransaction(),'se conserva transacción exterior para rollback de pruebas');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
echo "Aprobadas: $total. PostgreSQL local real; datos ficticios revertidos.\n";
