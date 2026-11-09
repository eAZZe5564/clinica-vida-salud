<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/Mantenimiento.php';

use App\Config\Mantenimiento;

$n = 0;
$check = static function (bool $ok, string $message) use (&$n): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$n;
};
$key = 'a' . substr(bin2hex(random_bytes(32)), 1);
$hash = hash('sha256', $key);
foreach (['1', 'true', 'on', 'ON', ' typo ', 'yes', 'OFFF'] as $v) {
    $check(Mantenimiento::activo($v, 'local'), 'El modo no conserva el cierre.');
}
foreach (['0', 'false', 'off', ' OFF '] as $v) {
    $check(!Mantenimiento::activo($v, 'production'), 'No se puede desactivar expresamente.');
}
foreach ([null, '', ' '] as $v) {
    $check(Mantenimiento::activo($v, 'production'), 'Producción debe cerrar por defecto.');
    $check(!Mantenimiento::activo($v, 'local'), 'Local debe conservar su funcionamiento habitual.');
}
$check(Mantenimiento::accesoPruebas($key, $hash), 'Clave válida rechazada.');
foreach ([null, '', [], $hash, strtoupper($key), str_repeat('0', 64), $key . 'x'] as $bad) {
    $check(!Mantenimiento::accesoPruebas($bad, $hash), 'Clave inválida admitida.');
}
foreach ([null, '', [], $key, str_repeat('x', 64)] as $badHash) {
    $check(!Mantenimiento::accesoPruebas($key, $badHash), 'Configuración inválida permite acceso.');
}
foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE'] as $method) {
    foreach (['/login', '/dashboard', '/mis-citas', '/reportes/citas.pdf', '/index.php/pacientes', '/%68ealth', '/health/'] as $path) {
        $server = ['REQUEST_URI' => $path, 'REQUEST_METHOD' => $method];
        $check(Mantenimiento::respuesta($server, 'on', 'production', $hash) === 503, 'Ruta evade mantenimiento.');
        $check(Mantenimiento::respuesta($server + ['HTTP_X_CLINICA_MAINTENANCE_KEY' => $key], 'on', 'production', $hash) === null, 'Acceso privado no continúa.');
        $check(Mantenimiento::respuesta($server, 'off', 'production', null) === null, 'Modo apagado altera rutas.');
    }
}
foreach (['GET', 'HEAD'] as $method) {
    $check(Mantenimiento::respuesta(['REQUEST_URI' => '/health?sin_datos=1', 'REQUEST_METHOD' => $method], 'on', 'production', null) === 200, 'Salud no disponible.');
}
foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
    $s = ['REQUEST_URI' => '/health', 'REQUEST_METHOD' => $method];
    $check(Mantenimiento::respuesta($s, 'on', 'production', $hash) === 503, 'Método no permitido evade el cierre.');
    $check(Mantenimiento::respuesta($s, 'off', 'production', $hash) === 405, 'Salud acepta métodos de escritura.');
}
$check(Mantenimiento::respuesta(['REQUEST_URI' => '/login?key=' . $key, 'REQUEST_METHOD' => 'GET'], 'on', 'production', $hash) === 503, 'La URL habilita acceso privado.');
$check(Mantenimiento::respuesta(['REQUEST_URI' => '/login', 'REQUEST_METHOD' => 'GET', 'HTTP_X_ORIGINAL_URL' => '/health', 'HTTP_X_FORWARDED_URI' => '/health'], 'on', 'production', $hash) === 503, 'Se confía en una cabecera de ruta alternativa.');

$root = dirname(__DIR__);
$index = file_get_contents($root . '/public/index.php');
$guard = "require_once dirname(__DIR__) . '/app/Config/mantenimiento-http.php';";
$bootstrap = "require dirname(__DIR__) . '/app/Config/bootstrap.php';";
$check(substr_count($index, $guard) === 1 && strpos($index, $guard) < strpos($index, $bootstrap), 'El guard real no precede al bootstrap.');
$apache = file_get_contents($root . '/docker/apache/clinica.conf');
$check(str_contains($apache, 'php_admin_value auto_prepend_file /var/www/clinica/app/Config/mantenimiento-http.php'), 'Falta protección para PHP directo en Apache.');
$check(str_contains($apache, 'DocumentRoot /var/www/clinica/public') && str_contains($apache, 'FallbackResource /index.php'), 'Cambió el aislamiento público.');
$check(str_contains(file_get_contents($root . '/Dockerfile'), 'FROM php:8.4-apache-bookworm'), 'La directiva Apache requiere el runtime mod_php revisado.');
putenv('APP_MAINTENANCE=on');
require $root . '/app/Config/mantenimiento-http.php';
$check(PHP_SAPI === 'cli', 'El guard interceptó la consola.');
putenv('APP_MAINTENANCE');

echo json_encode(['suite' => 'mantenimiento_validation', 'aprobadas' => $n, 'conexion_bd' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
