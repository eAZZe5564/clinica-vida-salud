<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Config\Database;
use App\Repositories\UsuarioRepository;
use App\Repositories\RolRepository;
use App\Services\UsuarioService;

$pdo = Database::connection();
if (!$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='pacientes' AND column_name='sexo'")->fetchColumn()) {
    fwrite(STDERR, "PENDIENTE: requiere migración 002.\n"); exit(2);
}
$sufijo = bin2hex(random_bytes(8));
$servicio = new UsuarioService($pdo, new UsuarioRepository($pdo), new RolRepository($pdo));
$idsUsuarios = [];
$idsPacientes = [];
$proceso = null;
$total = 0;
$ok = static function (bool $valor, string $nombre) use (&$total): void {
    if (!$valor) { throw new RuntimeException($nombre); }
    $total++; echo "OK: $nombre\n";
};
$puerto = random_int(30000, 45000);
$base = 'http://127.0.0.1:' . $puerto;
$http = static function (string $ruta, string $metodo = 'GET', string $cookie = '', array $datos = []) use ($base): array {
    $headers = 'Accept: text/html' . ($cookie ? "\r\nCookie: PHPSESSID=$cookie" : '');
    $opciones = ['method' => $metodo, 'header' => $headers, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5];
    if ($metodo === 'POST') {
        $opciones['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
        $opciones['content'] = http_build_query($datos);
    }
    $body = @file_get_contents($base . $ruta, false, stream_context_create(['http' => $opciones]));
    $headers = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $match);
    $cookieNueva = $cookie;
    $location = '';
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie: PHPSESSID=([^;]*)/i', $header, $c)) { $cookieNueva = $c[1]; }
        if (stripos($header, 'Location: ') === 0) { $location = substr($header, 10); }
    }
    return ['status' => (int) ($match[1] ?? 0), 'body' => $body ?: '', 'cookie' => $cookieNueva, 'location' => $location];
};
$csrf = static function (string $body): string {
    if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $body, $match)) { throw new RuntimeException('Falta CSRF'); }
    return $match[1];
};
try {
    // Proceso directo: al terminar se cierra exactamente el servidor de esta suite.
    $proceso = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', dirname(__DIR__) . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']], $pipes, dirname(__DIR__));
    if (!is_resource($proceso)) { throw new RuntimeException('No se pudo iniciar PHP'); }
    fclose($pipes[0]);
    for ($i = 0; $i < 30; $i++) {
        usleep(100000);
        if ($http('/login')['status'] === 200) { break; }
    }
    $ok($http('/pacientes')['status'] === 303, 'listado requiere sesión');
    $ok($http('/pacientes/nuevo')['status'] === 303, 'registro requiere sesión');
    $ok($http('/pacientes', 'POST')['status'] === 303, 'escritura requiere sesión');
    $roles = (new RolRepository($pdo))->listar();
    foreach ($roles as $rol) {
        $nombre = $rol['nombre'];
        $correo = 'pacientes-test-' . $nombre . '-' . $sufijo . '@example.invalid';
        $password = 'Prueba' . bin2hex(random_bytes(8)) . '91';
        $idsUsuarios[] = $servicio->crear($correo, $password, (int) $rol['id']);
        $login = $http('/login');
        $sesion = $http('/login', 'POST', $login['cookie'], ['correo' => $correo, 'contrasena' => $password, '_csrf' => $csrf($login['body'])]);
        $cookie = $sesion['cookie'];
        $ok($sesion['status'] === 303, "login $nombre");
        $autorizado = in_array($nombre, ['administrador', 'recepcionista'], true);
        $ok($http('/pacientes', 'GET', $cookie)['status'] === ($autorizado ? 200 : 403), "permiso listado $nombre");
        if (!$autorizado) {
            foreach (['/pacientes/nuevo', '/pacientes/1', '/pacientes/1/editar', '/pacientes/1/estado'] as $ruta) {
                $ok($http($ruta, 'GET', $cookie)['status'] === 403, "GET $ruta denegado a $nombre");
            }
            foreach (['/pacientes', '/pacientes/1/editar', '/pacientes/1/estado'] as $ruta) {
                $ok($http($ruta, 'POST', $cookie)['status'] === 403, "POST $ruta denegado a $nombre");
            }
            continue;
        }
        $form = $http('/pacientes/nuevo', 'GET', $cookie);
        $token = $csrf($form['body']);
        $datos = ['nombres' => 'Paciente' . $sufijo, 'apellidos' => 'HTTP ficticio', 'documento_identidad' => '00' . $sufijo . $nombre,
            'fecha_nacimiento' => '2000-01-01', 'sexo' => 'no_especificado', '_csrf' => $token];
        $ok($http('/pacientes', 'POST', $cookie, $datos + ['id' => '1'])['status'] === 303, "alta HTTP $nombre");
        $q = $pdo->prepare('SELECT id FROM pacientes WHERE documento_identidad = :ci');
        $q->execute(['ci' => strtoupper($datos['documento_identidad'])]);
        $id = (int) $q->fetchColumn();
        if ($id) { $idsPacientes[] = $id; }
        $ok($id > 0, 'alta persistida en PostgreSQL');
        $ruta = '/pacientes/' . $id;
        $detalle = $http($ruta, 'GET', $cookie);
        $ok($detalle['status'] === 200 && str_contains($detalle['body'], 'Este paciente todavía no tiene citas registradas'), 'detalle sin citas');
        $ok($http('/pacientes?q=' . urlencode($datos['documento_identidad']), 'GET', $cookie)['status'] === 200, 'búsqueda HTTP');
        $invalido = $http('/pacientes', 'POST', $cookie, array_replace($datos, ['nombres' => '   ', 'fecha_nacimiento' => '2999-01-01', 'correo_contacto' => 'invalido']));
        $ok($invalido['status'] === 422 && str_contains($invalido['body'], 'Este campo es obligatorio.') && str_contains($invalido['body'], 'Ingresa un correo'), 'errores junto a campos y fecha futura');
        $ok($http('/pacientes', 'POST', $cookie, $datos)['status'] === 422, 'CI duplicado HTTP');
        foreach (['/pacientes', $ruta . '/editar', $ruta . '/estado'] as $escritura) {
            $sinToken = $datos; unset($sinToken['_csrf']);
            $ok($http($escritura, 'POST', $cookie, $sinToken)['status'] === 419, "CSRF en $escritura");
        }
        $ok($http($ruta . '/editar', 'POST', $cookie, array_replace($datos, ['nombres' => '<script>Prueba</script>', 'id' => '1', 'usuario_id' => '1']))['status'] === 303, 'edición HTTP ignora identificadores enviados');
        $detalle = $http($ruta, 'GET', $cookie);
        $ok(str_contains($detalle['body'], '&lt;script&gt;Prueba&lt;/script&gt;') && !str_contains($detalle['body'], '<script>Prueba</script>'), 'salida HTML escapa XSS');
        $confirmacion = $http($ruta . '/estado', 'GET', $cookie);
        $q = $pdo->prepare('SELECT activo, usuario_id FROM pacientes WHERE id = :id');
        $q->execute(['id' => $id]); $fila = $q->fetch();
        $ok($confirmacion['status'] === 200 && $fila['activo'] && $fila['usuario_id'] === null, 'confirmación GET no cambia estado ni vincula cuenta');
        $ok($http($ruta . '/estado', 'POST', $cookie, ['_csrf' => $token, 'activo' => '0'])['status'] === 303, "desactivación $nombre");
        $q->execute(['id' => $id]); $ok(!$q->fetch()['activo'], 'paciente conservado inactivo');
        $ok($http($ruta . '/estado', 'POST', $cookie, ['_csrf' => $token, 'activo' => '1'])['status'] === 303, "reactivación $nombre");
        $ok($http('/pacientes/' . PHP_INT_MAX, 'GET', $cookie)['status'] === 404, 'paciente inexistente devuelve 404');
        $ok($http($ruta, 'POST', $cookie, $datos)['status'] === 405, 'detalle rechaza POST');
        $ok($http('/pacientes', 'DELETE', $cookie)['status'] === 405, 'no existe eliminación física HTTP');
        $ok($http('/logout', 'POST', $cookie, ['_csrf' => $token])['status'] === 303, 'cierre de sesión');
        $ok($http('/pacientes', 'GET', $cookie)['status'] === 303, 'pacientes inaccesible después de salir');
    }
} finally {
    if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
    foreach ($idsPacientes as $id) {
        $q = $pdo->prepare('DELETE FROM pacientes WHERE id = :id'); $q->execute(['id' => $id]);
    }
    foreach ($idsUsuarios as $id) {
        $q = $pdo->prepare('DELETE FROM usuarios WHERE id = :id'); $q->execute(['id' => $id]);
    }
}
echo "Aprobadas: $total. Solo se eliminaron registros ficticios creados por esta suite.\n";
