<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/bootstrap.php';

use App\Config\Database;
use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use App\Services\UsuarioService;

$pdo = Database::connection();
$usuarioRepo = new UsuarioRepository($pdo);
$rolRepo = new RolRepository($pdo);
$servicio = new UsuarioService($pdo, $usuarioRepo, $rolRepo);
$roles = [];
foreach ($rolRepo->listar() as $rol) {
    $roles[$rol['nombre']] = (int) $rol['id'];
}

$sufijo = bin2hex(random_bytes(8));
$correoAdmin = 'http-admin-' . $sufijo . '@example.invalid';
$correoRecepcion = 'http-recepcion-' . $sufijo . '@example.invalid';
$correoNuevo = 'http-nuevo-' . $sufijo . '@example.invalid';
$correoEditado = 'http-editado-' . $sufijo . '@example.invalid';
$correoInactivo = 'http-inactivo-' . $sufijo . '@example.invalid';
$contrasenaAdmin = 'HttpAdmin!' . random_int(10000000, 99999999);
$contrasenaRecepcion = 'HttpRecepcion!' . random_int(10000000, 99999999);
$contrasenaNuevo = 'HttpUsuario!' . random_int(10000000, 99999999);
$contrasenaInactivo = 'HttpInactivo!' . random_int(10000000, 99999999);
$adminId = null;
$recepcionId = null;
$nuevoId = null;
$proceso = null;
$salidas = 0;

$comprobar = static function (bool $condicion, string $nombre) use (&$salidas): void {
    if (!$condicion) {
        throw new RuntimeException('Falló la prueba HTTP: ' . $nombre);
    }
    $salidas++;
    echo "OK: {$nombre}\n";
};

$cookies = static function (array $cabeceras, string $anterior = ''): string {
    foreach ($cabeceras as $cabecera) {
        if (stripos($cabecera, 'Set-Cookie:') === 0) {
            if (preg_match('/PHPSESSID=([^;]*)/', $cabecera, $coincidencias) === 1) {
                return $coincidencias[1];
            }
        }
    }
    return $anterior;
};

$solicitar = static function (
    string $base,
    string $ruta,
    string $metodo = 'GET',
    string $cookie = '',
    array $datos = []
): array {
    $cabeceras = ["Accept: text/html"];
    if ($cookie !== '') {
        $cabeceras[] = 'Cookie: PHPSESSID=' . $cookie;
    }
    $opciones = [
        'http' => [
            'method' => $metodo,
            'header' => implode("\r\n", $cabeceras),
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ];
    if ($metodo === 'POST') {
        $opciones['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
        $opciones['http']['content'] = http_build_query($datos);
    }

    $contexto = stream_context_create($opciones);
    $cuerpo = file_get_contents($base . $ruta, false, $contexto);
    $respuestaHeaders = $http_response_header ?? [];
    $estado = 0;
    if (isset($respuestaHeaders[0]) && preg_match('/\s(\d{3})\s/', $respuestaHeaders[0], $coincidencias)) {
        $estado = (int) $coincidencias[1];
    }

    return [
        'status' => $estado,
        'body' => $cuerpo === false ? '' : $cuerpo,
        'headers' => $respuestaHeaders,
    ];
};

$token = static function (string $html): string {
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $coincidencias) !== 1) {
        throw new RuntimeException('No se encontró token CSRF en el formulario.');
    }
    return html_entity_decode($coincidencias[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

try {
    $adminId = $servicio->crear($correoAdmin, $contrasenaAdmin, $roles['administrador']);
    $recepcionId = $servicio->crear(
        $correoRecepcion,
        $contrasenaRecepcion,
        $roles['recepcionista']
    );
    $inactivoId = $servicio->crear($correoInactivo, $contrasenaInactivo, $roles['paciente']);
    $servicio->cambiarEstado($inactivoId, $adminId, false);

    $puerto = random_int(19000, 29000);
    $base = 'http://127.0.0.1:' . $puerto;
    // Proceso directo: en Windows proc_terminate debe cerrar PHP, no solo cmd.exe.
    $comando = [PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', dirname(__DIR__) . '/public'];
    $proceso = proc_open($comando, [
        0 => ['pipe', 'r'],
        1 => ['file', 'NUL', 'a'],
        2 => ['file', 'NUL', 'a'],
    ], $tuberias, dirname(__DIR__));
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar el servidor PHP para pruebas.');
    }
    fclose($tuberias[0]);

    $respuesta = null;
    for ($intento = 0; $intento < 30; $intento++) {
        usleep(100000);
        $respuesta = $solicitar($base, '/login');
        if ($respuesta['status'] === 200) {
            break;
        }
    }
    $comprobar($respuesta['status'] === 200, 'pantalla de acceso HTTP disponible');
    $cookieAnonima = $cookies($respuesta['headers']);
    $csrfAnonimo = $token($respuesta['body']);
    $comprobar($cookieAnonima !== '', 'cookie de sesión emitida');
    $comprobar(strlen($csrfAnonimo) === 64, 'formulario de ingreso genera token CSRF');
    $cabeceraCookie = implode("\n", $respuesta['headers']);
    $comprobar(
        stripos($cabeceraCookie, 'httponly') !== false
            && stripos($cabeceraCookie, 'samesite=lax') !== false,
        'cookie usa HttpOnly y SameSite'
    );
    $inicioPublico = $solicitar($base, '/');
    $comprobar(
        $inicioPublico['status'] === 200
            && str_contains($inicioPublico['body'], 'PHP está funcionando'),
        'página inicial pública continúa funcionando'
    );

    $sinSesion = $solicitar($base, '/dashboard');
    $comprobar(
        $sinSesion['status'] === 303
            && in_array('Location: /login?error=sesion', $sinSesion['headers'], true),
        'ruta privada redirige sin sesión'
    );
    $sinAdmin = $solicitar($base, '/admin/users', 'GET', $cookieAnonima);
    $comprobar($sinAdmin['status'] === 303, 'administración requiere autenticación');

    $loginPage = $solicitar($base, '/login');
    $cookieFallos = $cookies($loginPage['headers']);
    $intentoMalo = $solicitar($base, '/login', 'POST', $cookieFallos, [
        '_csrf' => $token($loginPage['body']),
        'correo' => $correoAdmin,
        'contrasena' => 'Incorrecta' . random_int(100000, 999999),
    ]);
    $comprobar($intentoMalo['status'] === 303, 'login con contraseña incorrecta rechazado');
    $falloContrasena = $solicitar($base, '/login?error=credenciales', 'GET', $cookieFallos);
    $mensajeContrasena = $falloContrasena['body'];

    $loginPage = $solicitar($base, '/login', 'GET', $cookieFallos);
    $cookieFallos = $cookies($loginPage['headers'], $cookieFallos);
    $loginInactivo = $solicitar($base, '/login', 'POST', $cookieFallos, [
        '_csrf' => $token($loginPage['body']),
        'correo' => $correoInactivo,
        'contrasena' => $contrasenaInactivo,
    ]);
    $comprobar($loginInactivo['status'] === 303, 'login de usuario inactivo rechazado');
    $falloInactivo = $solicitar($base, '/login?error=credenciales', 'GET', $cookieFallos);
    preg_match('/<div class="notice notice-error" role="status">\s*(.*?)\s*<\/div>/s', $mensajeContrasena, $mensaje1);
    preg_match('/<div class="notice notice-error" role="status">\s*(.*?)\s*<\/div>/s', $falloInactivo['body'], $mensaje2);
    $comprobar(
        isset($mensaje1[1], $mensaje2[1]) && $mensaje1[1] === $mensaje2[1],
        'usuario inactivo y contraseña incorrecta muestran mensaje genérico igual'
    );

    $loginPage = $solicitar($base, '/login');
    $cookieAnonima = $cookies($loginPage['headers']);
    $loginAdmin = $solicitar($base, '/login', 'POST', $cookieAnonima, [
        '_csrf' => $token($loginPage['body']),
        'correo' => $correoAdmin,
        'contrasena' => $contrasenaAdmin,
    ]);
    $cookieAdmin = $cookies($loginAdmin['headers'], $cookieAnonima);
    $comprobar(
        $loginAdmin['status'] === 303
            && in_array('Location: /dashboard', $loginAdmin['headers'], true)
            && $cookieAdmin !== $cookieAnonima,
        'login válido regenera identificador de sesión'
    );
    $panel = $solicitar($base, '/dashboard', 'GET', $cookieAdmin);
    $comprobar($panel['status'] === 200 && str_contains($panel['body'], 'Panel personal'), 'panel autenticado HTTP');

    $adminLista = $solicitar($base, '/admin/users', 'GET', $cookieAdmin);
    $comprobar($adminLista['status'] === 200, 'administrador accede a listado de usuarios');
    $edicionInexistente = $solicitar($base, '/admin/users/99999999/edit', 'GET', $cookieAdmin);
    $comprobar($edicionInexistente['status'] === 404, 'cuenta inexistente devuelve 404');
    $nuevaPagina = $solicitar($base, '/admin/users/new', 'GET', $cookieAdmin);
    $cookieAdmin = $cookies($nuevaPagina['headers'], $cookieAdmin);
    $crearSinCsrf = $solicitar($base, '/admin/users', 'POST', $cookieAdmin, [
        'correo' => $correoNuevo,
        'rol_id' => $roles['recepcionista'],
        'contrasena' => $contrasenaNuevo,
    ]);
    $comprobar($crearSinCsrf['status'] === 419, 'CSRF bloquea creación sin token');
    $csrfConTipoIncorrecto = $solicitar($base, '/admin/users', 'POST', $cookieAdmin, [
        '_csrf[]' => 'token',
    ]);
    $comprobar($csrfConTipoIncorrecto['status'] === 419, 'CSRF rechaza token con tipo inválido');

    $crear = $solicitar($base, '/admin/users', 'POST', $cookieAdmin, [
        '_csrf' => $token($nuevaPagina['body']),
        'correo' => $correoNuevo,
        'rol_id' => $roles['recepcionista'],
        'contrasena' => $contrasenaNuevo,
    ]);
    $comprobar($crear['status'] === 303, 'administrador crea cuenta desde el formulario');
    $comprobar(
        in_array('Location: /admin/users?mensaje=usuario-creado', $crear['headers'], true),
        'alta redirige a confirmación de éxito'
    );
    $confirmacionAlta = $solicitar($base, '/admin/users?mensaje=usuario-creado', 'GET', $cookieAdmin);
    $comprobar(
        str_contains($confirmacionAlta['body'], 'La cuenta se creó correctamente.'),
        'muestra confirmación de creación'
    );
    $consultaNueva = $pdo->prepare('SELECT id FROM usuarios WHERE correo = :correo');
    $consultaNueva->execute(['correo' => $correoNuevo]);
    $nuevoId = (int) $consultaNueva->fetchColumn();
    $comprobar($nuevoId > 0, 'cuenta creada persistió en PostgreSQL');

    $editarPagina = $solicitar($base, '/admin/users/' . $nuevoId . '/edit', 'GET', $cookieAdmin);
    $editar = $solicitar($base, '/admin/users/' . $nuevoId . '/edit', 'POST', $cookieAdmin, [
        '_csrf' => $token($editarPagina['body']),
        'correo' => $correoEditado,
        'rol_id' => $roles['paciente'],
        'contrasena' => '',
    ]);
    $comprobar($editar['status'] === 303, 'administrador modifica cuenta');
    $confirmarEdicion = $pdo->prepare(
        'SELECT correo, r.nombre
         FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = :id'
    );
    $confirmarEdicion->execute(['id' => $nuevoId]);
    $filaEditada = $confirmarEdicion->fetch();
    $comprobar(
        $filaEditada['correo'] === $correoEditado && $filaEditada['nombre'] === 'paciente',
        'cambios de correo y rol se guardaron'
    );

    $estadoPagina = $solicitar($base, '/admin/users', 'GET', $cookieAdmin);
    $csrfEstado = $token($estadoPagina['body']);
    $desactivar = $solicitar($base, '/admin/users/' . $nuevoId . '/status', 'POST', $cookieAdmin, [
        '_csrf' => $csrfEstado,
        'activo' => '0',
    ]);
    $comprobar($desactivar['status'] === 303, 'administrador desactiva cuenta');
    $estadoConsulta = $pdo->prepare('SELECT activo FROM usuarios WHERE id = :id');
    $estadoConsulta->execute(['id' => $nuevoId]);
    $comprobar(!$estadoConsulta->fetchColumn(), 'cuenta desactivada queda inactiva en PostgreSQL');
    $activar = $solicitar($base, '/admin/users/' . $nuevoId . '/status', 'POST', $cookieAdmin, [
        '_csrf' => $csrfEstado,
        'activo' => '1',
    ]);
    $comprobar($activar['status'] === 303, 'administrador reactiva cuenta');
    $estadoConsulta->execute(['id' => $nuevoId]);
    $comprobar((bool) $estadoConsulta->fetchColumn(), 'cuenta vuelve a quedar activa');

    $entradaRecepcion = $solicitar($base, '/login');
    $cookieRecepcionAnonima = $cookies($entradaRecepcion['headers']);
    $loginRecepcion = $solicitar($base, '/login', 'POST', $cookieRecepcionAnonima, [
        '_csrf' => $token($entradaRecepcion['body']),
        'correo' => $correoRecepcion,
        'contrasena' => $contrasenaRecepcion,
    ]);
    $cookieRecepcion = $cookies($loginRecepcion['headers'], $cookieRecepcionAnonima);
    $comprobar($loginRecepcion['status'] === 303, 'recepcionista inicia sesión');
    $adminDenegado = $solicitar($base, '/admin/users', 'GET', $cookieRecepcion);
    $comprobar($adminDenegado['status'] === 403, 'rol no administrador recibe 403');
    $escalamiento = $solicitar($base, '/admin/users', 'POST', $cookieRecepcion, [
        '_csrf' => 'token-invalido',
        'correo' => 'escalamiento@example.invalid',
        'rol_id' => $roles['administrador'],
        'contrasena' => 'Escalamiento123!',
    ]);
    $comprobar($escalamiento['status'] === 403, 'rol no administrador no puede escalar privilegios');

    $csrfLogout = $solicitar($base, '/dashboard', 'GET', $cookieAdmin);
    $logoutSinCsrf = $solicitar($base, '/logout', 'POST', $cookieAdmin, []);
    $comprobar($logoutSinCsrf['status'] === 419, 'CSRF bloquea cierre de sesión sin token');
    $logout = $solicitar($base, '/logout', 'POST', $cookieAdmin, [
        '_csrf' => $token($csrfLogout['body']),
    ]);
    $comprobar($logout['status'] === 303, 'cierre de sesión protegido');
    $paginaCierre = $solicitar($base, '/login?mensaje=cierre-sesion');
    $comprobar(
        str_contains($paginaCierre['body'], 'Has cerrado sesión correctamente.'),
        'muestra confirmación de cierre'
    );
    $despuesLogout = $solicitar($base, '/dashboard', 'GET', $cookieAdmin);
    $comprobar($despuesLogout['status'] === 303, 'sesión cerrada ya no accede al panel');

    $css = $solicitar($base, '/assets/css/app.css');
    $comprobar(
        $css['status'] === 200 && str_contains($css['body'], '@media (max-width: 760px)'),
        'hoja de estilos responsiva servida'
    );
} catch (Throwable $error) {
    fwrite(STDERR, sprintf(
        "Prueba HTTP interrumpida (%s): %s\n",
        get_class($error),
        $error->getMessage()
    ));
    throw $error;
} finally {
    if (is_resource($proceso)) {
        proc_terminate($proceso);
        proc_close($proceso);
    }

    $limpieza = $pdo->prepare(
        'DELETE FROM usuarios WHERE correo IN (:admin, :recepcion, :nuevo, :editado, :inactivo)'
    );
    $limpieza->execute([
        'admin' => $correoAdmin,
        'recepcion' => $correoRecepcion,
        'nuevo' => $correoNuevo,
        'editado' => $correoEditado,
        'inactivo' => $correoInactivo,
    ]);
}

echo "Aprobadas: {$salidas}. Cuentas sintéticas eliminadas al terminar.\n";
