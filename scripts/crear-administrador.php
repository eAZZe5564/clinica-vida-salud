<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/Config/bootstrap.php';

use App\Config\Database;
use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use App\Services\UsuarioService;

$correo = trim((string) fgets(STDIN));
$contrasena = rtrim((string) fgets(STDIN), "\r\n");

try {
    $pdo = Database::connection();
    $repositorio = new UsuarioRepository($pdo);
    $servicio = new UsuarioService($pdo, $repositorio, new RolRepository($pdo));
    $id = $servicio->crearAdministradorInicial($correo, $contrasena);
    fwrite(STDOUT, "Cuenta administradora inicial creada correctamente (ID {$id}).\n");
} catch (DomainException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $error) {
    error_log(sprintf('Falló la inicialización de administrador (%s).', get_class($error)));
    fwrite(STDERR, "No se pudo crear la cuenta administradora. Revisa la configuración local.\n");
    exit(1);
} finally {
    unset($contrasena);
}
