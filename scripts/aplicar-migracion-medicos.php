<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Config\Env;
if (Env::get('DB_HOST') !== '127.0.0.1' || Env::get('DB_NAME') !== 'clinica_vida_salud_dev') {
    fwrite(STDERR, "Se requiere la base local esperada.\n"); exit(1);
}
$pdo = App\Config\Database::connection();
try {
    $pdo->beginTransaction();
    $pdo->exec("SET LOCAL lock_timeout = '5s'");
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/003_medicos_especialidades.sql'));
    $pdo->commit();
    echo "Migración 003 aplicada localmente.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'Migración revertida. Código: ' . $e->getCode() . ". Comprobar compatibilidad o aplicación previa.\n");
    exit(1);
}
