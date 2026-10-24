<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Config\Database;
use App\Repositories\MedicoRepository;
use App\Repositories\EspecialidadRepository;
use App\Services\MedicoService;
use App\Services\EspecialidadService;
use App\Services\CatalogoValidacionException;

$pdo = Database::connection();
if (!$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name='medicos' AND column_name='correo_contacto' AND table_schema='public'")->fetchColumn()) {
    fwrite(STDERR,"PENDIENTE: requiere migración 003 autorizada y aplicada.\n"); exit(2);
}
$mr = new MedicoRepository($pdo); $er = new EspecialidadRepository($pdo);
$ms = new MedicoService($pdo,$mr,$er); $es = new EspecialidadService($pdo,$er);
$total = 0;
$ok = static function (bool $valor, string $nombre) use (&$total): void { if (!$valor) { throw new RuntimeException($nombre); } $total++; echo "OK: $nombre\n"; };
$rechaza = static function (callable $accion, string $campo, string $nombre) use ($ok): void {
    try { $accion(); $ok(false,$nombre); } catch (CatalogoValidacionException $e) { $ok(isset($e->errores[$campo]),$nombre); }
};
$s = bin2hex(random_bytes(6));
$pdo->beginTransaction();
try {
    $a = $es->guardar(['nombre' => 'Cardiología ' . $s]);
    $b = $es->guardar(['nombre' => 'Medicina ' . $s, 'descripcion' => 'Ficticia']);
    $ok($es->obtener($a)['activo'], 'crear especialidad activa');
    $rechaza(fn() => $es->guardar(['nombre' => ' CARDIOLOGÍA   ' . strtoupper($s) . ' ']), 'nombre', 'especialidad duplicada normalizada');
    $rechaza(fn() => $es->guardar(['nombre' => 'CARDIOLOGÍA ' . $s], $b), 'nombre', 'edición no duplica especialidad');
    $es->guardar(['nombre' => 'Medicina editada ' . $s, 'descripcion' => 'Cambio'], $b);
    $ok($es->obtener($b)['descripcion'] === 'Cambio', 'editar especialidad');
    $ok($er->listar($s, 1)['total'] === 2, 'buscar especialidades');
    $base = ['nombres' => 'Nombre' . $s,'apellidos' => 'Apellido' . $s,'numero_colegiatura' => '00' . $s,'especialidades' => [$a,$b]];
    $id = $ms->guardar($base);
    $m = $ms->obtener($id);
    $ok($m['activo'] && $m['usuario_id'] === null, 'crear médico activo sin cuenta');
    $usuarios = new App\Services\UsuarioService($pdo,new App\Repositories\UsuarioRepository($pdo),new App\Repositories\RolRepository($pdo));
    $roles = [];
    foreach ((new App\Repositories\RolRepository($pdo))->listar() as $rol) { $roles[$rol['nombre']] = (int) $rol['id']; }
    $cuenta = $usuarios->crear('medico-test-' . $s . '@example.invalid','PruebaMedico91!',$roles['medico']);
    $cuentaInvalida = $usuarios->crear('recepcion-test-' . $s . '@example.invalid','PruebaRecepcion91!',$roles['recepcionista']);
    $rechaza(fn() => $ms->guardar(array_replace($base,['usuario_id'=>$cuentaInvalida]),$id),'usuario_id','rechaza cuenta con rol no médico');
    $ms->guardar(array_replace($base,['usuario_id'=>$cuenta]),$id);
    $ok((int)$ms->obtener($id)['usuario_id']===$cuenta,'asociación opcional con cuenta médica existente');
    $rechaza(fn() => $ms->guardar(array_replace($base,['numero_colegiatura'=>'VINCULO' . $s,'usuario_id'=>$cuenta])),'usuario_id','no vincula misma cuenta a dos médicos');
    $ms->guardar($base,$id);
    $ok($ms->obtener($id)['usuario_id']===null,'desvincula sin eliminar cuenta');
    $ok(count($mr->especialidades($id)) === 2, 'asignar múltiples especialidades');
    $rechaza(fn() => $ms->guardar(array_replace($base,['numero_colegiatura' => ' 00 ' . strtoupper($s)])), 'numero_colegiatura', 'registro profesional duplicado normalizado');
    $otro = $ms->guardar(array_replace($base,['numero_colegiatura' => 'OTRO' . $s]));
    $rechaza(fn() => $ms->guardar($base,$otro),'numero_colegiatura','edición no duplica registro profesional');
    foreach (['nombres','apellidos','numero_colegiatura'] as $campo) { $ok($mr->listar($base[$campo], null, '', 1)['total'] >= 1, 'buscar por ' . $campo); }
    $ok($mr->listar('', $a, 'activo', 1)['total'] === 2, 'filtro especialidad y estado');
    $ms->guardar(array_replace($base,['telefono' => '70000000','correo_contacto' => 'ficticio@example.invalid']), $id);
    $ok($ms->obtener($id)['telefono'] === '70000000','editar contacto');
    $rechaza(fn() => $ms->asignar($id,[$a,$a]), 'especialidades', 'rechaza asociaciones duplicadas');
    $rechaza(fn() => $ms->asignar($id,[]), 'especialidades', 'activo no pierde última especialidad');
    $antes = $mr->especialidades($id);
    $rechaza(fn() => $ms->guardar(array_replace($base,['nombres' => 'NoPersistir','especialidades' => [$a, PHP_INT_MAX]]),$id), 'especialidades', 'operación compuesta inválida rechazada');
    $ok($ms->obtener($id)['nombres'] === $base['nombres'] && $mr->especialidades($id) === $antes, 'fallo conserva datos y asociaciones');
    $ms->asignar($id,[$a]);
    $ok(count($mr->especialidades($id)) === 1 && $es->obtener($b)['activo'], 'quitar asociación conserva catálogo');
    $rechaza(fn() => $es->cambiarEstado($a,false),'activo','no desactiva única especialidad de médico activo');
    $ms->asignar($id,[$a,$b]); $es->cambiarEstado($a,false);
    $ok(!$es->obtener($a)['activo'] && count($mr->especialidades($id)) === 2,'desactivar especialidad conserva asociación');
    $tercero = $ms->guardar(array_replace($base,['numero_colegiatura' => 'TERCERO' . $s,'especialidades' => [$b]]));
    $rechaza(fn() => $ms->asignar($tercero,[$a,$b]),'especialidades','no asigna nueva especialidad inactiva');
    $ms->asignar($id,[$a,$b]); $ok(true,'puede conservar inactiva previamente asignada');
    $ms->cambiarEstado($id,false);
    $ok(!$ms->obtener($id)['activo'] && count($mr->especialidades($id)) === 2,'desactivar médico conserva asociaciones');
    $ok($mr->listar($base['nombres'],null,'inactivo',1)['total'] === 1,'filtro de inactivos');
    $ms->asignar($id,[$a]);
    $rechaza(fn() => $ms->cambiarEstado($id,true),'especialidades','no reactiva sin especialidad activa');
    $es->cambiarEstado($a,true); $ms->cambiarEstado($id,true);
    $ok($ms->obtener($id)['activo'],'reactiva con especialidad activa');
    $q = $pdo->prepare("INSERT INTO pacientes(nombres,apellidos,documento_identidad) VALUES ('Paciente ficticio','Prueba',:ci) RETURNING id"); $q->execute(['ci' => $s]); $p = (int) $q->fetchColumn();
    $q = $pdo->prepare("INSERT INTO citas(paciente_id,medico_id,fecha_hora_inicio,estado) VALUES (:p,:m,'2020-01-01 12:00+00','atendida') RETURNING id"); $q->execute(['p'=>$p,'m'=>$id]); $c = (int) $q->fetchColumn();
    $ms->cambiarEstado($id,false);
    $q = $pdo->prepare('SELECT estado FROM citas WHERE id=:id'); $q->execute(['id'=>$c]); $ok($q->fetchColumn() === 'atendida','cita histórica conservada sin cambiar estado');
    $pdo->exec('SAVEPOINT prueba_trigger');
    try { $q=$pdo->prepare("INSERT INTO citas(paciente_id,medico_id,fecha_hora_inicio) VALUES (:p,:m,now()+interval '20 days')"); $q->execute(['p'=>$p,'m'=>$id]); $ok(false,'inactivo'); }
    catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT prueba_trigger'); $ok($e->getCode()==='23514','trigger rechaza cita con médico inactivo'); }
    $pdo->exec('RELEASE SAVEPOINT prueba_trigger');
    foreach ([
        ["INSERT INTO especialidades(nombre) VALUES (:v)", ' CARDIOLOGÍA  ' . strtoupper($s), 'índice especialidad normalizada'],
        ["INSERT INTO medicos(nombres,apellidos,numero_colegiatura) VALUES ('Ficticio','Prueba',:v)", ' 00 ' . strtolower($s), 'índice registro normalizado'],
    ] as [$sql,$valor,$nombre]) {
        $pdo->exec('SAVEPOINT prueba_unique');
        try { $q=$pdo->prepare($sql); $q->execute(['v'=>$valor]); $ok(false,$nombre); }
        catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT prueba_unique'); $ok($e->getCode()==='23505',$nombre); }
        $pdo->exec('RELEASE SAVEPOINT prueba_unique');
    }
    $pdo->exec('SAVEPOINT prueba_relacion');
    try { $q=$pdo->prepare('INSERT INTO medicos_especialidades(medico_id,especialidad_id) VALUES (:m,:e)'); $q->execute(['m'=>$id,'e'=>$a]); $ok(false,'PK'); }
    catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT prueba_relacion'); $ok($e->getCode()==='23505','PK rechaza asociación duplicada directamente'); }
    $pdo->exec('RELEASE SAVEPOINT prueba_relacion');
    for ($i=0;$i<21;$i++) { $ms->guardar(array_replace($base,['numero_colegiatura'=>$s . '-' . $i])); }
    $resultado=$mr->listar($s,null,'',1); $ok(count($resultado['filas'])===20 && $resultado['paginas']===2,'paginación de médicos');
    $ok($mr->listar('%' . $s,null,'',1)['total']===0,'búsqueda escapa comodines');
    for ($i=0;$i<21;$i++) { $es->guardar(['nombre'=>'Especialidad paginada ' . $s . '-' . $i]); }
    $resultado=$er->listar('Especialidad paginada ' . $s,1);
    $ok(count($resultado['filas'])===20 && $resultado['paginas']===2,'paginación de especialidades');
    try { $ms->obtener(PHP_INT_MAX); $ok(false,'inexistente'); } catch (OutOfBoundsException) { $ok(true,'médico inexistente'); }
} finally { $pdo->rollBack(); }
echo "Aprobadas: $total. Todos los registros se revirtieron; pueden existir saltos de secuencia.\n";
