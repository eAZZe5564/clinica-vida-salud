<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Config\Database;
use App\Repositories\PacienteRepository;
use App\Services\PacienteService;
use App\Services\PacienteValidacionException;

$pdo = Database::connection();
if (!$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='pacientes' AND column_name='sexo'")->fetchColumn()) {
    fwrite(STDERR, "PENDIENTE: requiere migración 002 autorizada y aplicada.\n"); exit(2);
}
$repo = new PacienteRepository($pdo);
$servicio = new PacienteService($repo);
$total = 0;
$ok = static function (bool $valor, string $nombre) use (&$total): void {
    if (!$valor) { throw new RuntimeException($nombre); }
    $total++; echo "OK: $nombre\n";
};
$sufijo = bin2hex(random_bytes(6));
$base = ['nombres' => 'Paciente' . $sufijo, 'apellidos' => 'Apellido' . $sufijo,
    'documento_identidad' => '00' . $sufijo, 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'no_especificado'];
$duplicado = static function (array $datos, ?int $id = null) use ($servicio, $ok): void {
    try { $servicio->guardar($datos, $id); $ok(false, 'CI duplicado rechazado'); }
    catch (PacienteValidacionException $e) { $ok(isset($e->errores['documento_identidad']), 'CI duplicado normalizado rechazado'); }
};
$pdo->beginTransaction();
try {
    $id = $servicio->guardar($base);
    $paciente = $servicio->obtener($id);
    $ok($paciente['usuario_id'] === null && $paciente['activo'], 'alta activa sin crear cuenta');
    $ok($paciente['documento_identidad'] === strtoupper($base['documento_identidad']), 'CI textual normalizado persistido');
    $duplicado(array_replace($base, ['documento_identidad' => ' 00 ' . strtoupper($sufijo)]));
    foreach (['documento_identidad', 'nombres', 'apellidos'] as $campo) {
        $ok(in_array($id, array_column($repo->listar($base[$campo], 1)['filas'], 'id')), 'busca por ' . $campo);
    }
    $segundo = $servicio->guardar(array_replace($base, ['documento_identidad' => 'OTRO' . $sufijo]));
    $duplicado(array_replace($base, ['documento_identidad' => 'OTRO' . $sufijo]), $id);
    $servicio->guardar(array_replace($base, ['nombres' => 'Editado', 'correo_contacto' => 'ficticio@example.invalid', 'direccion' => 'Dirección ficticia']), $id);
    $ok($servicio->obtener($id)['nombres'] === 'Editado', 'edición válida');
    // Relación histórica ficticia: se revierte junto con todo el escenario.
    $q = $pdo->prepare('INSERT INTO medicos(nombres, apellidos, numero_colegiatura) VALUES (:n, :a, :c) RETURNING id');
    $q->execute(['n' => 'Médico ficticio', 'a' => 'Prueba', 'c' => 'TEST' . $sufijo]);
    $medico = (int) $q->fetchColumn();
    $q = $pdo->prepare("INSERT INTO citas(paciente_id, medico_id, fecha_hora_inicio, estado) VALUES (:p, :m, '2020-01-01 12:00:00+00', 'atendida') RETURNING id");
    $q->execute(['p' => $id, 'm' => $medico]);
    $cita = (int) $q->fetchColumn();
    $servicio->cambiarEstado($id, false);
    $ok(!$servicio->obtener($id)['activo'], 'desactivación lógica conserva paciente');
    $historial = $repo->citas($id, 1);
    $ok((int) $historial['filas'][0]['id'] === $cita && $historial['filas'][0]['estado'] === 'atendida', 'historial y estado de cita conservados');
    $pdo->exec('SAVEPOINT reserva_inactiva');
    try {
        $q = $pdo->prepare("INSERT INTO citas(paciente_id, medico_id, fecha_hora_inicio) VALUES (:p, :m, now() + interval '10 days')");
        $q->execute(['p' => $id, 'm' => $medico]);
        $ok(false, 'bloquea reserva para inactivo');
    } catch (PDOException $e) {
        $pdo->exec('ROLLBACK TO SAVEPOINT reserva_inactiva');
        $ok($e->getCode() === '23514', 'trigger existente bloquea nueva cita para inactivo');
    }
    $pdo->exec('RELEASE SAVEPOINT reserva_inactiva');
    $servicio->cambiarEstado($id, true);
    $ok($servicio->obtener($id)['activo'], 'reactivación');
    $pdo->exec('SAVEPOINT duplicado_directo');
    try {
        $q = $pdo->prepare('INSERT INTO pacientes(nombres, apellidos, documento_identidad) VALUES (:n, :a, :d)');
        $q->execute(['n' => 'Prueba', 'a' => 'Prueba', 'd' => ' 00 ' . strtolower($sufijo)]);
        $ok(false, 'índice único protege escritura directa');
    } catch (PDOException $e) {
        $pdo->exec('ROLLBACK TO SAVEPOINT duplicado_directo');
        $ok($e->getCode() === '23505', 'PostgreSQL rechaza duplicado normalizado directamente');
    }
    $pdo->exec('RELEASE SAVEPOINT duplicado_directo');
    $ok(count($repo->citas($segundo, 1)['filas']) === 0, 'paciente sin citas');
    for ($i = 0; $i < 21; $i++) {
        $servicio->guardar(array_replace($base, ['documento_identidad' => $sufijo . '-' . $i]));
    }
    $resultado = $repo->listar('Apellido' . $sufijo, 1);
    $ok(count($resultado['filas']) === 20 && $resultado['paginas'] === 2, 'paginación limitada a 20');
    $ok($repo->listar('%' . $sufijo, 1)['total'] === 0, 'comodines de búsqueda tratados como texto');
    try { $servicio->obtener(PHP_INT_MAX); $ok(false, 'inexistente'); }
    catch (OutOfBoundsException) { $ok(true, 'detalle inexistente controlado'); }
} finally {
    $pdo->rollBack();
}
echo "Aprobadas: $total. Registros revertidos; las secuencias pueden tener saltos normales.\n";
