<?php
declare(strict_types=1);
require __DIR__ . '/ReprogramacionPrueba.php';
use App\Services\CitaException;
$pdo=cu15Conexion();$prueba=new ReprogramacionPrueba($pdo);$t=$prueba->t;$f=$t->fixture;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $accion,int $estado,string $nombre)use($ok):void{
    try{$accion();$ok(false,$nombre);}catch(CitaException $e){$ok($e->estadoHttp===$estado,$nombre);}
};
$pdo->beginTransaction();
try{
    $a=$f->usuario('administrador');$r=$f->usuario('recepcionista');$u=$f->usuario('medico');$p=$f->paciente();$m=$f->medico();$e=$f->especialidades[0];$h=$t->horario($m);
    $c=$t->servicio->crear($t->datos($m,$p,$e)+['motivo'=>'Motivo ficticio CU15'],$a)['id'];$antes=$prueba->fila($c);
    $datos=$prueba->datos($c,'09:00');$revision=$prueba->servicio->previsualizar($c,$datos);
    $ok($prueba->fila($c)===$antes,'revisión no escribe');
    $turnos=$prueba->servicio->turnos($prueba->servicio->obtener($c),$datos['fecha']);
    $ok(count($turnos['turnos'])===8,'CU15 excluye solo la propia cita de la ocupación');
    $resultado=$prueba->servicio->reprogramar($c,$datos,$r);$despues=$prueba->fila($c);
    $ok($resultado['id']===$c&&$resultado['codigo']===App\Models\Cita::codigo($c),'misma cita y código');
    unset($antes['fecha_hora_inicio'],$despues['fecha_hora_inicio']);$ok($antes===$despues,'paciente médico especialidad duración motivo estado y creación intactos');
    $ok(str_contains($resultado['inicio'],'09:00:00'),'nuevo horario guardado');
    $q=$pdo->prepare('SELECT * FROM citas_historial WHERE cita_id=:id');$q->execute(['id'=>$c]);$hist=$q->fetchAll();
    $ok(count($hist)===1&&(int)$hist[0]['usuario_id']===$r&&$hist[0]['tipo']==='reprogramacion','historial único con actor de recepción');
    $ok(new DateTimeImmutable($hist[0]['inicio_anterior'])==new DateTimeImmutable($t->lunes->format('Y-m-d').' 08:00',App\Models\HorarioAtencion::zona()),'historial conserva horario anterior');
    $libres=$t->disponibilidad->consultar($m,$datos['fecha'],$e);$horas=array_map(static fn($v)=>(new DateTimeImmutable($v['inicio']))->format('H:i'),$libres['turnos']);
    $ok(in_array('08:00',$horas,true)&&!in_array('09:00',$horas,true),'CU12 libera anterior y ocupa nuevo');
    $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($pdo),new App\Repositories\HorarioRepository($pdo),['id'=>$a,'rol'=>'administrador']);
    $ok($consulta->detalle($c)['hora_inicio']==='09:00','CU14 muestra nuevo horario');
    $rechazar(fn()=>$prueba->servicio->reprogramar(PHP_INT_MAX,$datos,$a),404,'cita inexistente');
    foreach(['cancelada','atendida'] as $estado){
        $pdo->prepare('UPDATE citas SET estado=:s WHERE id=:id')->execute(['s'=>$estado,'id'=>$c]);$original=$prueba->fila($c);
        $rechazar(fn()=>$prueba->servicio->reprogramar($c,$datos,$a),409,'rechazo estado '.$estado);$ok($prueba->fila($c)===$original,'estado rechazado conserva cita');
    }
    $pdo->prepare("UPDATE citas SET estado='programada' WHERE id=:id")->execute(['id'=>$c]);
    $base=$prueba->datos($c,'10:00');$original=$prueba->fila($c);
    foreach([['fecha'=>'2000-01-01'],['hora_inicio'=>'10:15'],['hora_inicio'=>[]],['fecha'=>[]]] as $cambio){$rechazar(fn()=>$prueba->servicio->reprogramar($c,array_replace($base,$cambio),$a),422,'fecha/hora inválida');}
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,array_replace($base,['hora_inicio'=>'09:00']),$a),409,'horario actual informa sin cambios');
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,array_replace($base,['hora_inicio'=>'12:00']),$a),409,'fuera de atención');
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,array_replace($base,['fecha'=>$t->lunes->modify('+1 day')->format('Y-m-d')]),$a),409,'día sin atención');
    $t->servicio->crear($t->datos($m,$p,$e,'10:00'),$a);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$base,$a),409,'turno ocupado');
    $ok($prueba->fila($c)===$original,'errores conservan íntegramente la cita');
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$prueba->datos($c,'11:00'),$u),403,'servicio deniega rol médico');
    $pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id')->execute(['id'=>$m]);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$base,$a),422,'médico inactivo');$pdo->prepare('UPDATE medicos SET activo=true WHERE id=:id')->execute(['id'=>$m]);
    $pdo->prepare('DELETE FROM medicos_especialidades WHERE medico_id=:m AND especialidad_id=:e')->execute(['m'=>$m,'e'=>$e]);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$base,$a),422,'asociación retirada');$pdo->prepare('INSERT INTO medicos_especialidades(medico_id,especialidad_id) VALUES (:m,:e)')->execute(['m'=>$m,'e'=>$e]);
    $pdo->prepare('UPDATE especialidades SET activo=false WHERE id=:id')->execute(['id'=>$e]);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$base,$a),422,'especialidad inactiva');$pdo->prepare('UPDATE especialidades SET activo=true WHERE id=:id')->execute(['id'=>$e]);
    $pdo->prepare('UPDATE horarios_atencion SET activo=false WHERE id=:id')->execute(['id'=>$h]);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$prueba->datos($c,'11:00'),$a),409,'horario desactivado');$pdo->prepare('UPDATE horarios_atencion SET activo=true WHERE id=:id')->execute(['id'=>$h]);
    // Descanso 10:30–11:00: dos tramos válidos, sin cambiar el horario de las citas.
    $t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'10:30'],$h);
    $t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'11:00','hora_fin'=>'12:00']);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$prueba->datos($c,'10:30'),$a),409,'descanso no seleccionable');
    foreach(['UPDATE citas SET fecha_hora_inicio','INSERT INTO citas_historial'] as $fallar){
        $proxy=new Cu15FalloPDO($pdo,$fallar);
        $servicio=new App\Services\ReprogramacionService($proxy,new App\Repositories\CitaRepository($proxy),$t->disponibilidad);
        $antesFallo=$prueba->fila($c);$eventos=(int)$pdo->query('SELECT count(*) FROM citas_historial')->fetchColumn();
        $rechazar(fn()=>$servicio->reprogramar($c,$prueba->datos($c,'11:00'),$a),422,'fallo inyectado de persistencia: '.$fallar);
        $ok($prueba->fila($c)===$antesFallo&&(int)$pdo->query('SELECT count(*) FROM citas_historial')->fetchColumn()===$eventos,'rollback de cita e historial tras fallo SQL');
    }
    $stale=$prueba->datos($c,'11:00');$prueba->servicio->reprogramar($c,$stale,$a);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,array_replace($stale,['hora_inicio'=>'11:30']),$r),409,'formulario desactualizado no sobrescribe cambio');
    $idaVuelta=$prueba->datos($c,'11:30');$prueba->servicio->reprogramar($c,$idaVuelta,$a);
    $prueba->servicio->reprogramar($c,$prueba->datos($c,'11:00'),$a);
    $rechazar(fn()=>$prueba->servicio->reprogramar($c,$idaVuelta,$a),409,'referencia de historial rechaza formulario viejo tras ida y vuelta al mismo horario');
    $ok($pdo->inTransaction(),'savepoints conservan transacción exterior para rollback de pruebas');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
echo "Aprobadas: $total. Datos ficticios revertidos íntegramente.\n";
