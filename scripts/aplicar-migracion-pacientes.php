<?php
declare(strict_types=1);
// Ejecutar únicamente después de obtener autorización para 002.
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
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/002_pacientes_cu04_cu06.sql'));
    $pdo->commit();
    echo "Migración 002 aplicada en la base local.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'Migración revertida. Código: ' . $e->getCode() . ". Revisa compatibilidad o si ya fue aplicada.\n");
    exit(1);
}
