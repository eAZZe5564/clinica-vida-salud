<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/bootstrap.php';
require_once dirname(__DIR__) . '/app/Config/helpers.php';

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\UsuarioService;

$pdo = Database::connection();
$usuariosRepo = new UsuarioRepository($pdo);
$rolesRepo = new RolRepository($pdo);
$auth = new AuthService($usuariosRepo);
$usuarios = new UsuarioService($pdo, $usuariosRepo, $rolesRepo);
$pruebas = 0;

$comprobar = static function (bool $condicion, string $nombre) use (&$pruebas): void {
    if (!$condicion) {
        throw new RuntimeException('Falló la prueba: ' . $nombre);
    }
    $pruebas++;
    echo "OK: {$nombre}\n";
};

$esperarErrorDominio = static function (callable $accion, string $nombre) use ($comprobar): void {
    try {
        $accion();
    } catch (DomainException) {
        $comprobar(true, $nombre);
        return;
    }

    $comprobar(false, $nombre);
};

$sufijo = bin2hex(random_bytes(6));
$correoAdmin = 'test-admin-' . $sufijo . '@example.invalid';
$contrasenaAdmin = 'PruebaSegura91!';
$pdo->beginTransaction();

try {
    $adminId = $usuarios->crearAdministradorInicial($correoAdmin, $contrasenaAdmin);
    $comprobar($auth->autenticar($correoAdmin, $contrasenaAdmin)?->id === $adminId, 'login válido verifica password_hash/password_verify');
    $comprobar($auth->autenticar($correoAdmin, 'Incorrecta91') === null, 'contraseña incorrecta rechazada');
    $comprobar($auth->autenticar('no-existe-' . $sufijo . '@example.invalid', 'Incorrecta91') === null, 'cuenta inexistente no se revela');

    $roles = [];
    foreach ($rolesRepo->listar() as $rol) {
        $roles[$rol['nombre']] = (int) $rol['id'];
    }
    $comprobar(count($roles) === 4, 'se cargaron los cuatro roles fijos');

    $correoOtro = 'test-user-' . $sufijo . '@example.invalid';
    $otroId = $usuarios->crear($correoOtro, 'CuentaPrueba92!', $roles['recepcionista']);
    $comprobar(
        count($usuarios->listar('test-user-' . $sufijo)) === 1,
        'búsqueda parametrizada de usuarios'
    );

    $correoEditado = 'test-editado-' . $sufijo . '@example.invalid';
    $usuarios->actualizar($otroId, $correoEditado, $roles['paciente'], 'CuentaNueva93!');
    $actualizado = $usuarios->obtener($otroId);
    $comprobar(
        $actualizado['correo'] === $correoEditado && $actualizado['rol_nombre'] === 'paciente',
        'modificación de correo y rol'
    );
    $comprobar(
        $auth->autenticar($correoEditado, 'CuentaNueva93!')?->id === $otroId,
        'actualización de contraseña con hash'
    );

    $esperarErrorDominio(
        static fn() => $usuarios->actualizar($otroId, $correoEditado, 999999, null),
        'rechaza un rol que no existe'
    );
    $esperarErrorDominio(
        static fn() => $usuarios->crear('invalid-email', 'CuentaPrueba94!', $roles['paciente']),
        'rechaza correo inválido'
    );
    $esperarErrorDominio(
        static fn() => $usuarios->crear(
            'test-weak-' . $sufijo . '@example.invalid',
            'weak',
            $roles['administrador']
        ),
        'rechaza contraseña débil'
    );
    $esperarErrorDominio(
        static fn() => $usuarios->crear(
            'test-long-' . $sufijo . '@example.invalid',
            'A1' . str_repeat('b', 71),
            $roles['paciente']
        ),
        'rechaza contraseñas que superarían el límite bcrypt'
    );
    $comprobar(
        $auth->autenticar($correoAdmin, str_repeat('x', 73)) === null,
        'login rechaza contraseñas que exceden el límite bcrypt'
    );

    $inactivoCorreo = 'test-inactivo-' . $sufijo . '@example.invalid';
    $inactivoId = $usuarios->crear($inactivoCorreo, 'CuentaInactiva95!', $roles['medico']);
    $usuarios->cambiarEstado($inactivoId, $adminId, false);
    $comprobar($auth->autenticar($inactivoCorreo, 'CuentaInactiva95!') === null, 'usuario inactivo no puede iniciar sesión');
    $comprobar(!$usuarios->obtener($inactivoId)['activo'], 'desactivación lógica conserva la cuenta');

    $esperarErrorDominio(
        static fn() => $usuarios->cambiarEstado($adminId, $adminId, false),
        'impide que el administrador se desactive a sí mismo'
    );
    $esperarErrorDominio(
        static fn() => $usuarios->cambiarEstado($adminId, $otroId, false),
        'protege a la última cuenta administradora activa'
    );
    $esperarErrorDominio(
        static fn() => $usuarios->actualizar(
            $adminId,
            $correoAdmin,
            $roles['recepcionista'],
            null
        ),
        'impide degradar al último administrador activo'
    );

    $middleware = new AuthMiddleware($auth);
    $_SESSION = [];
    $comprobar($middleware->usuarioActual() === null, 'middleware no acepta sesión anónima');
    $_SESSION = [
        'usuario_id' => $adminId,
        'autenticado_en' => time(),
        'actividad_en' => time(),
    ];
    $comprobar(
        $middleware->usuarioActual()['rol'] === 'administrador',
        'middleware refresca rol desde PostgreSQL'
    );
    $_SESSION = ['csrf_token' => str_repeat('a', 64)];
    $comprobar(validar_csrf(str_repeat('a', 64)), 'CSRF acepta token vigente');
    $comprobar(!validar_csrf(str_repeat('b', 64)), 'CSRF rechaza token inválido');
} catch (Throwable $error) {
    fwrite(STDERR, sprintf(
        "Prueba interrumpida (%s, código %s).\n",
        get_class($error),
        (string) $error->getCode()
    ));
    throw $error;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION = [];
}

echo "Aprobadas: {$pruebas}. Todos los registros de prueba se revirtieron.\n";
