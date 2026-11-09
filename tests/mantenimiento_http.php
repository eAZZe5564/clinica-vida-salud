<?php

declare(strict_types=1);

// HTTP real con código exportado de Git y usuarios/PDO en memoria. Sin PostgreSQL.
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir() . '/clinica-mantenimiento-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700, true)) { throw new RuntimeException('No se pudo preparar el aislamiento.'); }
$processes = [];
$checks = 0;
$byVersion = [];
$closedVersions = [];
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($label); }
    ++$checks;
};
$run = static function (array $command, string $cwd): void {
    $pipes = [];
    $p = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, null, ['bypass_shell' => true]);
    if (!is_resource($p)) { throw new RuntimeException('No se pudo iniciar una herramienta local.'); }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($p) !== 0) { throw new RuntimeException('Falló una herramienta de exportación local.'); }
};
$request = static function (string $base, string $path, string $method = 'GET', array $headers = [], array $form = []): array {
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $form === [] ? '' : http_build_query($form),
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
    ]]);
    $body = @file_get_contents($base . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $m);
    $normalized = [];
    foreach (array_slice($responseHeaders, 1) as $h) {
        if (str_contains($h, ':')) {
            [$name, $value] = explode(':', $h, 2);
            $normalized[strtolower($name)][] = trim($value);
        }
    }
    return ['status' => (int) ($m[1] ?? 0), 'headers' => $normalized, 'body' => $body === false ? '' : $body];
};
$csrf = static function (array $response): string {
    if (preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $response['body'], $m) !== 1) {
        throw new RuntimeException('Falta CSRF en el formulario aislado.');
    }
    return $m[1];
};
$cookie = static function (array $response, string $previous = ''): string {
    foreach ($response['headers']['set-cookie'] ?? [] as $value) {
        if (str_starts_with($value, 'PHPSESSID=')) { return explode(';', $value, 2)[0]; }
    }
    return $previous;
};
$start = static function (string $export, string $sessionDir, string $events, string $mode, string $passwordHash, string $keyHash, bool $prepend = true, string $environment = 'local') use (&$processes, $root, $temporary, $request): string {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
    if ($socket === false) { throw new RuntimeException('No hay puerto local disponible.'); }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $env = getenv();
    foreach (array_keys($env) as $name) {
        if (preg_match('/^(PG|DB_|DATABASE_URL)/i', $name)) { unset($env[$name]); }
    }
    $env = array_merge($env, [
        'APP_ENV' => $environment, 'APP_TIMEZONE' => 'America/La_Paz',
        'APP_MAINTENANCE' => $mode, 'APP_MAINTENANCE_TEST_HASH' => $keyHash,
        'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_NAME' => 'fixture_in_memory',
        'DB_USER' => 'fixture', 'DB_PASSWORD' => '',
        'MANTENIMIENTO_FIXTURE_ROOT' => $export,
        'MANTENIMIENTO_FIXTURE_EVENTS' => $events,
        'MANTENIMIENTO_FIXTURE_HASH' => $passwordHash,
    ]);
    $command = [PHP_BINARY, '-d', 'session.save_path=' . $sessionDir, '-d', 'display_errors=0'];
    if ($prepend) { array_push($command, '-d', 'auto_prepend_file=' . $root . '/tests/fixtures/mantenimiento_prepend.php'); }
    array_push($command, '-S', $address, '-t', $export . '/public');
    $pipes = [];
    $p = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes, $export, $env, ['bypass_shell' => true]);
    if (!is_resource($p)) { throw new RuntimeException('No arrancó el servidor HTTP aislado.'); }
    fclose($pipes[0]); $processes[] = $p;
    $base = 'http://' . $address;
    for ($attempt = 0; $attempt < 30; ++$attempt) {
        if ($request($base, '/health')['status'] === 200) { return $base; }
        usleep(100000);
    }
    throw new RuntimeException('El servidor aislado no respondió a /health.');
};
$routes = [
    '/', '/login', '/dashboard', '/admin/users', '/admin/users/1/status', '/pacientes', '/medicos', '/especialidades',
    '/horarios', '/disponibilidad', '/citas', '/citas/nueva', '/citas/1',
    '/citas/1/reprogramar', '/citas/1/cancelar', '/citas/1/confirmar',
    '/citas/1/llegada', '/citas/1/atencion', '/citas/1/inasistencia',
    '/agenda', '/agenda/semanal', '/pacientes/1/historial-citas', '/reportes/citas',
    '/reportes/citas/exportar/pdf', '/reportes/citas/exportar/csv',
    '/pacientes/1/perfil-clinico', '/pacientes/1/alergias',
    '/pacientes/1/antecedentes', '/pacientes/1/laboratorios',
    '/mis-citas', '/mis-citas/nueva', '/mis-citas/1/reprogramar', '/mis-citas/1/cancelar',
    '/voz', '/logout', '/index.php', '/index.php/pacientes', '/probe.php',
    '/app/Config/Database.php', '/database/migrations/001_initial.sql',
    '/.env', '/vendor/autoload.php', '/tests/mantenimiento_validation.php',
    '/%68ealth', '/health/',
];

try {
    $key = bin2hex(random_bytes(32));
    $password = 'Fixture!9' . bin2hex(random_bytes(12));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    foreach (['puente-ciclo1' => '1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37', 'completa-cu26' => '23b1175fe865021a102a890c0f5b267eabad5588'] as $version => $commit) {
        $initial = $checks;
        $export = $temporary . '/' . $version;
        mkdir($export, 0700); mkdir($export . '/sessions', 0700);
        $archive = $temporary . '/' . $version . '.tar';
        $run(['git', '-c', 'safe.directory=' . str_replace('\\', '/', $root), 'archive', '--format=tar', '--output=' . $archive, $commit], $root);
        $run(['tar', '-xf', $archive, '-C', $export], $root);
        unlink($archive);
        $check(!file_exists($export . '/.env'), $version . ': se exportó configuración privada.');
        $check(!file_exists($export . '/app/Config/DatabaseDiagnostic.php'), $version . ': se exportó un cambio protegido.');
        foreach (['app/Config/Mantenimiento.php', 'app/Config/mantenimiento-http.php', 'docker/apache/clinica.conf'] as $file) {
            copy($root . '/' . $file, $export . '/' . $file);
        }
        $index = file_get_contents($export . '/public/index.php');
        $needle = "require dirname(__DIR__) . '/app/Config/bootstrap.php';";
        $check(substr_count($index, $needle) === 1, 'No se puede insertar el guard de forma inequívoca.');
        file_put_contents($export . '/public/index.php', str_replace($needle, "require_once dirname(__DIR__) . '/app/Config/mantenimiento-http.php';\n" . $needle, $index));
        file_put_contents($export . '/public/probe.php', '<?php echo "PUBLIC_PHP_CANARY";');
        $events = $export . '/events.txt'; file_put_contents($events, '');
        $open = $start($export, $export . '/sessions', $events, 'off', $passwordHash, hash('sha256', $key));
        $closed = $start($export, $export . '/sessions', $events, 'on', $passwordHash, hash('sha256', $key));
        $closedVersions[] = $closed;
        $plain = $start($export, $export . '/sessions', $events, '', $passwordHash, '', false, 'production');
        $check($request($plain, '/login')['status'] === 503, 'El index sin auto_prepend debe cerrar en producción.');
        $check($request($plain, '/login', 'GET', ['X-Clinica-Maintenance-Key: ' . $key])['status'] === 503, 'Sin hash configurado debe ser imposible habilitar acceso de pruebas.');
        $check($request($plain, '/citas')['status'] === 503, 'La ruta alternativa sin auto_prepend no cierra.');
        $check($request($open, '/probe.php')['body'] === 'PUBLIC_PHP_CANARY', 'El PHP directo no funciona en modo normal.');
        $check($request($closed, '/health')['body'] === '{"status":"ok"}', 'La salud contiene datos o depende de PostgreSQL.');
        $check($request($closed, '/health', 'HEAD')['body'] === '', 'HEAD devuelve contenido.');
        $check($request($closed, '/health', 'POST')['status'] === 503, 'POST health evade cierre.');
        $check($request($closed, '/assets/css/app.css')['status'] === 200, 'Se bloquearon recursos públicos sin datos.');
        $check($request($open, '/health', 'POST')['status'] === 405, 'POST health no está restringido.');
        $check($request($closed, '/dashboard', 'GET', ['X-Clinica-Maintenance-Key: ' . str_repeat('0', 64)])['status'] === 503, 'Una clave inválida abre el sistema.');
        $bypass = ['X-Clinica-Maintenance-Key: ' . $key];
        $check($request($closed, '/dashboard', 'GET', $bypass)['status'] === 303, 'La clave de pruebas omite la autenticación.');
        $check($request($closed, '/login', 'POST', $bypass)['status'] === 419, 'La clave de pruebas omite CSRF.');
        $sessions = ['anonimo' => ''];
        foreach (['administrador', 'recepcionista', 'medico', 'paciente'] as $role) {
            $login = $request($open, '/login');
            $check($login['status'] === 200, $version . ': login no funciona con mantenimiento apagado.');
            $session = $cookie($login);
            $auth = $request($open, '/login', 'POST', ['Cookie: ' . $session, 'Content-Type: application/x-www-form-urlencoded'], ['_csrf' => $csrf($login), 'correo' => $role . '@example.invalid', 'contrasena' => $password]);
            $check($auth['status'] === 303 && ($auth['headers']['location'][0] ?? '') === '/dashboard', $version . ': login ficticio no funciona para ' . $role . '.');
            $session = $cookie($auth, $session);
            $sessions[$role] = $session;
            $dashboard = $request($open, '/dashboard', 'GET', ['Cookie: ' . $session]);
            $check($dashboard['status'] === 200, $version . ': dashboard no funciona para ' . $role . '.');
            $check(str_contains($dashboard['body'], $role . '@example.invalid'), 'La sesión no representa al usuario ficticio.');
            $allowed = $request($closed, '/dashboard', 'GET', array_merge($bypass, ['Cookie: ' . $session]));
            $check($allowed['status'] === 200, 'La clave privada no conserva una sesión válida.');
            $check($request($closed, '/logout', 'POST', array_merge($bypass, ['Cookie: ' . $session]))['status'] === 419, 'CSRF autenticado no se conserva.');
            $beforeForm = file_get_contents($events);
            $blockedForm = $request($closed, '/citas', 'POST', ['Cookie: ' . $session, 'Content-Type: application/x-www-form-urlencoded', 'X-Requested-With: XMLHttpRequest'], ['_csrf' => $csrf($dashboard), 'paciente_id' => '1', 'medico_id' => '1']);
            $check($blockedForm['status'] === 503, 'Un formulario autenticado con CSRF válido o AJAX evade el cierre.');
            $check(file_get_contents($events) === $beforeForm, 'El formulario cerrado alcanzó PostgreSQL.');
            if ($role !== 'administrador') {
                $check($request($closed, '/admin/users', 'GET', array_merge($bypass, ['Cookie: ' . $session]))['status'] === 403, 'La clave privada amplía el rol.');
            }
        }
        $before = file_get_contents($events);
        $sessionBefore = glob($export . '/sessions/*'); sort($sessionBefore);
        foreach ($sessions as $role => $session) {
            foreach ($routes as $path) {
                foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $method) {
                    $r = $request($closed, $path, $method, $session === '' ? [] : ['Cookie: ' . $session]);
                    $check($r['status'] === 503, $version . ': cierre incorrecto ' . $role . ' ' . $method . ' ' . $path . ' (' . $r['status'] . ').');
                    $check(($r['headers']['retry-after'][0] ?? '') === '120', 'Falta Retry-After.');
                    $check(str_contains($r['headers']['cache-control'][0] ?? '', 'no-store'), 'El cierre se podría almacenar en caché.');
                    $check(!isset($r['headers']['set-cookie']), 'El cierre inicia una sesión.');
                    $check(!str_contains($r['body'], '@example.invalid') && !str_contains($r['body'], 'PUBLIC_PHP_CANARY'), 'El cierre revela contenido interno.');
                    if ($method === 'HEAD') { $check($r['body'] === '', 'HEAD expone contenido.'); }
                }
            }
        }
        $check(file_get_contents($events) === $before, 'El cierre alcanzó la factoría de conexión o una consulta.');
        $sessionAfter = glob($export . '/sessions/*'); sort($sessionAfter);
        $check($sessionBefore === $sessionAfter, 'El cierre creó sesiones nuevas.');
        $check($request($closed, '/login', 'GET', ['X-Original-URL: /health'])['status'] === 503, 'Cabecera de ruta evade cierre.');
        foreach ($sessions as $role => $session) {
            if ($session === '') { continue; }
            $dashboard = $request($open, '/dashboard', 'GET', ['Cookie: ' . $session]);
            $out = $request($open, '/logout', 'POST', ['Cookie: ' . $session, 'Content-Type: application/x-www-form-urlencoded'], ['_csrf' => $csrf($dashboard)]);
            $check($out['status'] === 303, 'Logout válido no funciona en modo normal.');
            $check($request($open, '/dashboard', 'GET', ['Cookie: ' . $session])['status'] === 303, 'Logout no invalida la sesión.');
        }
        $byVersion[$version] = $checks - $initial;
        echo $version . ': ' . $byVersion[$version] . " comprobaciones HTTP aprobadas.\n";
    }
    // Ambas versiones coexisten: alternar destino no reabre el acceso.
    for ($i = 0; $i < 10; ++$i) {
        foreach ($closedVersions as $server) {
            $check($request($server, '/citas', 'POST')['status'] === 503, 'El solapamiento puente/completa reabre escrituras.');
        }
    }
    echo 'TOTAL: ' . $checks . " comprobaciones HTTP aprobadas; sin conexión PostgreSQL.\n";
} finally {
    foreach ($processes as $p) { proc_terminate($p); proc_close($p); }
    // Solo archivos creados en el directorio temporal aleatorio de esta ejecución.
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir()) { rmdir($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($temporary);
}
