<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Autocarga sin bootstrap: esta operación no lee .env ni inicia sesiones HTTP.
spl_autoload_register(static function (string $clase): void {
    if (str_starts_with($clase, 'App\\')) {
        $ruta = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
        if (is_file($ruta)) { require_once $ruta; }
    }
});
require __DIR__ . '/RenderDestino.php';

try {
    // Tres líneas por stdin; ningún secreto en argumentos, archivos o salida.
    $config = json_decode((string)fgets(STDIN), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($config)) { throw new DomainException('Configuración remota inválida.'); }
    $config = RenderDestino::validar($config);
    $correo = trim((string)fgets(STDIN));
    $contrasena = rtrim((string)fgets(STDIN), "\r\n");
    App\Services\PoliticaContrasena::validar($contrasena);
    $pdo = RenderDestino::conectar($config);
    RenderDestino::comprobar($pdo, $config);
    $servicio = new App\Services\UsuarioService($pdo, new App\Repositories\UsuarioRepository($pdo), new App\Repositories\RolRepository($pdo));
    $servicio->crearAdministradorInicial($correo, $contrasena);
    fwrite(STDOUT, "Primera cuenta administradora creada en la base Render confirmada.\n");
} catch (DomainException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    // No imprimir errores PDO, cadenas de conexión ni contenido recibido.
    fwrite(STDERR, "No se pudo inicializar el administrador remoto. Revisa conexión, CA, migraciones y catálogos.\n");
    exit(1);
} finally {
    unset($contrasena, $config);
}
