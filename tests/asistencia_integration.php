<?php
declare(strict_types=1);
require __DIR__.'/AsistenciaPrueba.php';
use App\Services\AsistenciaService;
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=new AsistenciaPrueba($pdo);$t=$prueba->t;$f=$t->fixture;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"FALLO: $n\n");throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $accion,int $estado,string $n)use($ok):void{try{$accion();$ok(false,$n);}catch(CitaException $e){$ok($e->estadoHttp===$estado,$n);}};
$pdo->beginTransaction();
try{
    $a=$f->usuario('administrador');$r=$f->usuario('recepcionista');$d=$f->usuario('medico');$otro=$f->usuario('medico');$pa=$f->usuario('paciente');
    $m=$f->medico($d);$p=$f->paciente();$e=$f->especialidades[0];$h=$t->horario($m);
    $crear=fn(string $hora)=>$t->servicio->crear($t->datos($m,$p,$e,$hora),$a)['id'];
    $antesInicio=$t->lunes->setTime(7,59,59);$inicio=$t->lunes->setTime(8,0);$fin=$inicio->modify('+30 minutes');
    $temprano=$prueba->servicio($antesInicio);$enInicio=$prueba->servicio($inicio);$enFin=$prueba->servicio($fin);
    $c=$crear('08:00');$original=$prueba->fila($c);$base=$prueba->datos($c);
    $resultado=$temprano->registrar($c,'llegada',$base,$r);
    $actual=$prueba->fila($c);$evento=$prueba->eventos($c)[0];
    $ok($resultado['estado']==='presente'&&$actual['estado']==='presente','llegada registra presente, no atendida');
    $ok($resultado['codigo']===App\Models\Cita::codigo($c),'conserva código de cita');
    unset($actual['estado'],$original['estado']);$ok($actual===$original,'todos los campos salvo estado se conservan');
    $ok($evento['tipo']==='llegada'&&$evento['estado_anterior']==='programada'&&$evento['estado_nuevo']==='presente','evento de llegada distinto de atención completada');
    $ok((int)$evento['usuario_id']===$r&&new DateTimeImmutable($evento['cambiado_en'])==$antesInicio,'responsable y momento del reloj simulado de llegada');
    $ok($evento['inicio_anterior']===$evento['inicio_nuevo']&&$evento['motivo_cancelacion']===null,'horario conservado y sin motivo clínico');
    $rechazar(fn()=>$temprano->registrar($c,'llegada',$base,$a),409,'segunda llegada rechazada');
    $rechazar(fn()=>$temprano->registrar($c,'atencion',$prueba->datos($c),$d),409,'no finaliza antes del inicio');
    $rechazar(fn()=>$enFin->registrar($c,'inasistencia',$prueba->datos($c),$r),409,'presente no puede registrarse como inasistencia');
    foreach([$a,$r,$pa] as $actor){$rechazar(fn()=>$enInicio->registrar($c,'atencion',$prueba->datos($c),$actor),403,'solo médico finaliza atención');}
    $rechazar(fn()=>$enInicio->obtener($c,'atencion',$otro),404,'médico ajeno no consulta acción de atención');
    $rechazar(fn()=>$enInicio->registrar($c,'atencion',$prueba->datos($c),$otro),404,'médico ajeno no modifica cita');
    $enInicio->registrar($c,'atencion',$prueba->datos($c),$d);$eventos=$prueba->eventos($c);
    $ok($prueba->fila($c)['estado']==='atendida'&&count($eventos)===2,'finalización desde presente registra atendida');
    $ok($eventos[1]['tipo']==='atencion'&&(int)$eventos[1]['usuario_id']===$d&&(int)$eventos[0]['usuario_id']===$r,'separación entre quien recibió y quien atendió');
    $ok(new DateTimeImmutable($eventos[1]['cambiado_en'])==$inicio,'permite finalizar exactamente desde el inicio');
    foreach(['llegada','atencion','inasistencia'] as $accion){$rechazar(fn()=>$enFin->registrar($c,$accion,$prueba->datos($c),$accion==='atencion'?$d:$a),409,'atendida es terminal para '.$accion);}
    $ok(count($prueba->eventos($c))===2,'rechazos no generan historial falso');
    $ok(count($t->disponibilidad->consultar($m,$t->lunes->format('Y-m-d'),$e)['turnos'])===7,'atendida conserva intervalo histórico en CU12');
    $rechazar(fn()=>$crear('08:00'),409,'CU13 no reserva intervalo atendido');
    foreach(['programada','confirmada','presente','atendida','inasistencia'] as $estado){
        $pdo->exec('SAVEPOINT choque_gist');$codigo=null;
        try{$q=$pdo->prepare('INSERT INTO citas(medico_id,paciente_id,especialidad_id,fecha_hora_inicio,estado) VALUES (:m,:p,:e,:inicio,:estado)');$q->execute(['m'=>$m,'p'=>$p,'e'=>$e,'inicio'=>$inicio->format('c'),'estado'=>$estado]);}
        catch(PDOException $error){$codigo=(string)$error->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT choque_gist');$pdo->exec('RELEASE SAVEPOINT choque_gist');}
        $ok($codigo==='23P01','GiST protege intervalo histórico contra estado '.$estado);
    }
    $c2=$crear('09:00');$base=$prueba->datos($c2);$fin2=$t->lunes->setTime(9,30);$tarde=$prueba->servicio($fin2);
    $rechazar(fn()=>$prueba->servicio($fin2->modify('-1 microsecond'))->registrar($c2,'inasistencia',$base,$a),409,'inasistencia no permitida ni un microsegundo antes del final');
    $tarde->registrar($c2,'inasistencia',$base,$a);$evento=$prueba->eventos($c2)[0];
    $ok($prueba->fila($c2)['estado']==='inasistencia'&&$evento['tipo']==='inasistencia','registra inasistencia exactamente al terminar');
    $ok(new DateTimeImmutable($evento['cambiado_en'])==$fin2&&(int)$evento['usuario_id']===$a,'momento y responsable de inasistencia');
    foreach(['llegada','atencion','inasistencia'] as $accion){$rechazar(fn()=>$tarde->registrar($c2,$accion,$prueba->datos($c2),$accion==='atencion'?$d:$r),409,'inasistencia es terminal para '.$accion);}
    $c3=$crear('10:00');$base=$prueba->datos($c3);$prueba->previa->c->servicio->cancelar($c3,$prueba->previa->c->datos($c3),$a);
    foreach(['llegada','atencion','inasistencia'] as $accion){$rechazar(fn()=>$enFin->registrar($c3,$accion,$base,$accion==='atencion'?$d:$r),409,'cancelada rechaza '.$accion);}
    $ok($crear('10:00')!==$c3,'cancelada continúa liberando la reserva');
    $c4=$crear('11:00');$base=$prueba->datos($c4);
    $rechazar(fn()=>$enFin->registrar(PHP_INT_MAX,'llegada',$base,$a),404,'cita inexistente');
    foreach([$d,$pa] as $actor){$rechazar(fn()=>$enFin->registrar($c4,'llegada',$base,$actor),403,'rol no autorizado para llegada');$rechazar(fn()=>$enFin->registrar($c4,'inasistencia',$base,$actor),403,'rol no autorizado para inasistencia');}
    $rechazar(fn()=>$enFin->registrar($c4,'atencion',$base,$d),409,'no finaliza sin llegada previa');
    foreach(['',['array']] as $accion){$rechazar(fn()=>$enFin->registrar($c4,'llegada',array_replace($base,['accion'=>$accion]),$a),422,'confirmación explícita requerida');}
    foreach(['',['array'],str_repeat('x',101)] as $token){$rechazar(fn()=>$enFin->registrar($c4,'llegada',array_replace($base,['original'=>$token]),$a),409,'referencia malformada rechazada');}
    foreach(['UPDATE citas SET estado=:nuevo','INSERT INTO citas_historial'] as $fallar){
        $proxy=new Cu15FalloPDO($pdo,$fallar);$servicio=$prueba->servicio($fin,$proxy);$antes=$prueba->fila($c4);
        $rechazar(fn()=>$servicio->registrar($c4,'llegada',$base,$r),503,'fallo SQL controlado: '.$fallar);
        $ok($prueba->fila($c4)===$antes&&$prueba->eventos($c4)===[],'rollback de estado y evento');
    }
    $prueba->previa->servicio->confirmar($c4,$prueba->previa->datos($c4),$a);
    $rechazar(fn()=>$enFin->registrar($c4,'llegada',$base,$r),409,'confirmación concurrente invalida pantalla anterior');
    $enFin->registrar($c4,'llegada',$prueba->datos($c4),$r);
    $ok($prueba->fila($c4)['estado']==='presente'&&count($prueba->eventos($c4))===2,'llegada desde confirmada con nuevo formulario');
    foreach([$c,$c2,$c4] as $id){
        $rechazar(fn()=>$prueba->previa->c->r->servicio->obtener($id),409,'CU15 rechaza presente o terminal');
        $rechazar(fn()=>$prueba->previa->c->servicio->obtener($id),409,'CU16 rechaza presente o terminal');
        $rechazar(fn()=>$prueba->previa->servicio->obtener($id),409,'CU17 rechaza presente o terminal');
    }
    try{$t->horarios->cambiarEstado($h,false);$ok(false,'CU11 protege intervalos vigentes');}catch(CatalogoValidacionException $error){$ok(isset($error->errores['citas']),'CU11 protege presente y atención sin terminar intervalo');}
    $q=$pdo->prepare('SELECT activo FROM horarios_atencion WHERE id=:id');$q->execute(['id'=>$h]);$ok($q->fetchColumn(),'rechazo CU11 conserva horario');
    $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($pdo),new App\Repositories\HorarioRepository($pdo),['id'=>$d,'rol'=>'medico']);
    $ok($consulta->detalle($c)['estado']==='atendida'&&$consulta->listar(['estado'=>'presente'])['total']===1,'CU14 médico consulta y filtra su asistencia');
    $repo=new App\Repositories\CitaRepository($pdo);$privado=$repo->historialAdministrativo($c,false);$completo=$repo->historialAdministrativo($c,true);
    $ok(!array_key_exists('responsable',$privado[0])&&array_key_exists('responsable',$completo[0]),'traza completa separada de marcas administrativas');
    $insertar=$pdo->prepare('INSERT INTO citas_historial(cita_id,tipo,inicio_anterior,inicio_nuevo,estado_anterior,estado_nuevo,usuario_id,cambiado_en) VALUES (:id,:tipo,:inicio,:nuevo,:anterior,:estado,:actor,:momento)');
    $hist=['id'=>$c4,'tipo'=>'atencion','inicio'=>$t->lunes->setTime(11,0)->format('c'),'nuevo'=>$t->lunes->setTime(11,0)->format('c'),'anterior'=>'presente','estado'=>'atendida','actor'=>$d,'momento'=>$t->lunes->setTime(11,0)->format('c')];
    foreach([['estado'=>'presente'],['anterior'=>'programada'],['nuevo'=>$fin->format('c')],['momento'=>$t->lunes->setTime(10,59,59)->format('c')]] as $cambio){
        $pdo->exec('SAVEPOINT evento_invalido');$codigo=null;
        try{$insertar->execute(array_replace($hist,$cambio));}catch(PDOException $error){$codigo=(string)$error->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT evento_invalido');$pdo->exec('RELEASE SAVEPOINT evento_invalido');}
        $ok($codigo==='23514','CHECK reales rechazan evento de asistencia incompatible');
    }
    $pdo->exec('SAVEPOINT evento_duplicado');$codigo=null;
    try{$ev=$prueba->eventos($c4)[1];$insertar->execute(['id'=>$c4,'tipo'=>'llegada','inicio'=>$ev['inicio_anterior'],'nuevo'=>$ev['inicio_nuevo'],'anterior'=>'confirmada','estado'=>'presente','actor'=>$r,'momento'=>$fin->format('c')]);}
    catch(PDOException $error){$codigo=(string)$error->getCode();}
    finally{$pdo->exec('ROLLBACK TO SAVEPOINT evento_duplicado');$pdo->exec('RELEASE SAVEPOINT evento_duplicado');}
    $ok($codigo==='23505','índice único real rechaza llegada duplicada');
    $ok($pdo->inTransaction(),'savepoints conservan transacción exterior para rollback');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
echo "Aprobadas: $total. Datos ficticios con reloj simulado explícito y rollback íntegro.\n";
