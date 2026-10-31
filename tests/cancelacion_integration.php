<?php
declare(strict_types=1);
require __DIR__.'/CancelacionPrueba.php';
use App\Services\CitaException;
$pdo=cu15Conexion();$prueba=new CancelacionPrueba($pdo);$t=$prueba->r->t;$f=$t->fixture;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $accion,int $estado,string $n)use($ok):void{
    try{$accion();$ok(false,$n);}catch(CitaException $e){$ok($e->estadoHttp===$estado,$n);}
};
$pdo->beginTransaction();
try{
    $a=$f->usuario('administrador');$r=$f->usuario('recepcionista');$u=$f->usuario('medico');$pa=$f->usuario('paciente');
    $m=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$h=$t->horario($m);
    $crear=fn(string $hora)=>$t->servicio->crear($t->datos($m,$p,$e,$hora),$a)['id'];
    $c=$crear('08:00');$antes=$prueba->r->fila($c);$datos=$prueba->datos($c,'Cambio administrativo ficticio');
    $desde=new DateTimeImmutable($pdo->query('SELECT clock_timestamp()')->fetchColumn());
    $resultado=$prueba->servicio->cancelar($c,$datos,$a);$despues=$prueba->r->fila($c);$eventos=$prueba->eventos($c);$evento=$eventos[0];
    $ok($resultado['estado']==='cancelada'&&$despues['estado']==='cancelada','cancelación válida cambia estado');
    $ok($resultado['codigo']===App\Models\Cita::codigo($c),'código conservado');
    unset($antes['estado'],$despues['estado']);$ok($antes===$despues,'todos los campos salvo estado se conservan, sin eliminación');
    $ok(count($eventos)===1&&$evento['tipo']==='cancelacion','un único evento de cancelación');
    $ok((int)$evento['usuario_id']===$a&&$evento['estado_anterior']==='programada'&&$evento['estado_nuevo']==='cancelada','actor y transición correctos');
    $ok($evento['inicio_anterior']===$evento['inicio_nuevo']&&$evento['inicio_nuevo']===$antes['fecha_hora_inicio'],'historial conserva horario');
    $ok($evento['motivo_cancelacion']==='Cambio administrativo ficticio','motivo administrativo persistido');
    $momento=new DateTimeImmutable($evento['cambiado_en']);$hasta=new DateTimeImmutable($pdo->query('SELECT clock_timestamp()')->fetchColumn());
    $ok($momento>=$desde&&$momento<=$hasta,'fecha real de operación con zona horaria');
    $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($pdo),new App\Repositories\HorarioRepository($pdo),['id'=>$a,'rol'=>'administrador']);
    $ok($consulta->detalle($c)['estado']==='cancelada','CU14 conserva detalle cancelado');
    $ok($consulta->listar(['codigo'=>$resultado['codigo'],'estado'=>'cancelada'])['total']===1,'CU14 filtra canceladas sin perder identidad');
    $turnos=$t->disponibilidad->consultar($m,$t->lunes->format('Y-m-d'),$e);
    $ok(count($turnos['turnos'])===8,'CU12 libera el turno cancelado');
    $nueva=$crear('08:00');$ok($nueva!==$c&&$prueba->r->fila($c)['estado']==='cancelada','CU13 reserva turno liberado sin conflicto GiST');
    $rechazar(fn()=>$prueba->r->servicio->obtener($c),409,'CU15 no reprograma canceladas');
    $rechazar(fn()=>$prueba->servicio->cancelar($c,$datos,$r),409,'segunda cancelación rechazada');
    $ok(count($prueba->eventos($c))===1,'reenvío no duplica historial');
    $rechazar(fn()=>$prueba->servicio->cancelar(PHP_INT_MAX,$datos,$a),404,'cita inexistente');
    $c2=$crear('09:00');$base=$prueba->datos($c2);
    $pdo->prepare("UPDATE citas SET estado='atendida' WHERE id=:id")->execute(['id'=>$c2]);
    $rechazar(fn()=>$prueba->servicio->cancelar($c2,$base,$a),409,'atendida no admite cancelación');
    $ok($prueba->eventos($c2)===[],'estado rechazado no genera historial');
    $c3=$crear('10:00');$base=$prueba->datos($c3);$original=$prueba->r->fila($c3);
    foreach([$u,$pa,PHP_INT_MAX] as $actor){$rechazar(fn()=>$prueba->servicio->cancelar($c3,$base,$actor),403,'servicio rechaza actor no autorizado');}
    $pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:id')->execute(['id'=>$r]);
    $rechazar(fn()=>$prueba->servicio->cancelar($c3,$base,$r),403,'actor inactivo rechazado');
    $pdo->prepare('UPDATE usuarios SET activo=true WHERE id=:id')->execute(['id'=>$r]);
    foreach([['accion'=>''],['accion'=>[]],['motivo_cancelacion'=>[]],['motivo_cancelacion'=>str_repeat('á',251)],['motivo_cancelacion'=>"salto\nlinea"],['motivo_cancelacion'=>"\xC3\x28"]] as $cambio){
        $rechazar(fn()=>$prueba->servicio->cancelar($c3,array_replace($base,$cambio),$a),422,'entrada malformada rechazada');
    }
    foreach(['',[],str_repeat('x',81)] as $token){$rechazar(fn()=>$prueba->servicio->cancelar($c3,array_replace($base,['original'=>$token]),$a),409,'referencia inválida rechazada');}
    $ok($prueba->r->fila($c3)===$original&&$prueba->eventos($c3)===[],'rechazos conservan cita e historial');
    foreach(["UPDATE citas SET estado='cancelada'",'INSERT INTO citas_historial'] as $fallar){
        $proxy=new Cu15FalloPDO($pdo,$fallar);$servicio=new App\Services\CancelacionService($proxy,new App\Repositories\CitaRepository($proxy));
        $rechazar(fn()=>$servicio->cancelar($c3,$base,$a),503,'fallo SQL controlado: '.$fallar);
        $ok($prueba->r->fila($c3)===$original&&$prueba->eventos($c3)===[],'rollback de estado e historial ante fallo SQL');
    }
    $prueba->r->servicio->reprogramar($c3,$prueba->r->datos($c3,'11:00'),$a);
    $rechazar(fn()=>$prueba->servicio->cancelar($c3,$base,$a),409,'cancelación vieja no afecta reprogramación confirmada');
    $ok(count($prueba->eventos($c3))===1&&$prueba->eventos($c3)[0]['tipo']==='reprogramacion','CU15 sigue registrando reprogramación tras 006');
    $prueba->servicio->cancelar($c3,$prueba->datos($c3,'  '),$r);
    $ok(count($prueba->eventos($c3))===2&&$prueba->eventos($c3)[1]['motivo_cancelacion']===null,'recepcionista cancela sin motivo');
    $c4=$crear('11:30');$base=$prueba->datos($c4);
    $pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id')->execute(['id'=>$p]);
    $pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id')->execute(['id'=>$m]);
    $pdo->prepare('UPDATE especialidades SET activo=false WHERE id=:id')->execute(['id'=>$e]);
    $prueba->servicio->cancelar($c4,$base,$a);
    $ok($prueba->r->fila($c4)['estado']==='cancelada','permite cancelar con catálogos inactivos');
    $q=$pdo->prepare('SELECT activo,hora_inicio,hora_fin FROM horarios_atencion WHERE id=:id');$q->execute(['id'=>$h]);$horario=$q->fetch();
    $ok($horario['activo']&&$horario['hora_inicio']==='08:00:00'&&$horario['hora_fin']==='12:00:00','CU11 conserva horarios intactos');
    // Probar los CHECK reales de 006 con datos propios y savepoints, sin DDL.
    $insertar=$pdo->prepare('INSERT INTO citas_historial(cita_id,tipo,inicio_anterior,inicio_nuevo,estado_anterior,estado_nuevo,usuario_id,motivo_cancelacion) VALUES (:c,:tipo,:anterior,:nuevo,:antes,:despues,:u,:motivo)');
    $eventoBase=['c'=>$c,'tipo'=>'cancelacion','anterior'=>$antes['fecha_hora_inicio'],'nuevo'=>$antes['fecha_hora_inicio'],'antes'=>'programada','despues'=>'cancelada','u'=>$a,'motivo'=>null];
    foreach([
        ['tipo'=>'otro'],['despues'=>'atendida'],['antes'=>'cancelada'],['nuevo'=>$t->lunes->setTime(9,0)->format('c')],
        ['motivo'=>''],['motivo'=>'   '],['motivo'=>str_repeat('a',251)],
        ['tipo'=>'reprogramacion','despues'=>'programada'],
        ['tipo'=>'reprogramacion','despues'=>'programada','nuevo'=>$t->lunes->setTime(9,0)->format('c'),'motivo'=>'no permitido'],
    ] as $cambio){
        $pdo->exec('SAVEPOINT evento_invalido');$estado=null;
        try{$insertar->execute(array_replace($eventoBase,$cambio));}catch(PDOException $error){$estado=(string)$error->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT evento_invalido');$pdo->exec('RELEASE SAVEPOINT evento_invalido');}
        $ok(in_array($estado,['23514','22001'],true),'PostgreSQL rechaza evento incompatible con 006');
    }
    $ok($prueba->servicio->motivo(str_repeat('á',250))===str_repeat('á',250),'acepta límite de 250 caracteres Unicode');
    $ok($prueba->servicio->motivo('')===null,'motivo vacío es opcional');
    $ok($pdo->inTransaction(),'se conserva transacción exterior para rollback de pruebas');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
echo "Aprobadas: $total. PostgreSQL local real; datos ficticios revertidos.\n";
