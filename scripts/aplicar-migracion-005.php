<?php
declare(strict_types=1);
// Exclusivo de desarrollo. No utiliza los asistentes de Render ni crea cuentas.
require dirname(__DIR__) . '/tests/Cu15Local.php';
$pdo = cu15Conexion();
try {
    $pdo->beginTransaction();
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_migracion_005'))");
    if ((int)$pdo->query('SHOW server_version_num')->fetchColumn() < 170000
        || (int)$pdo->query('SHOW server_version_num')->fetchColumn() >= 180000
        || $pdo->query("SELECT to_regclass('public.citas_historial')")->fetchColumn()) {
        throw new RuntimeException('Se requiere PostgreSQL 17 local sin migración 005 instalada.');
    }
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/005_citas_historial.sql'));
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    if ((int)$pdo->query('SELECT count(*) FROM citas_historial')->fetchColumn() !== 0) {
        throw new RuntimeException('El historial nuevo no está vacío.');
    }
    $pdo->commit();
    echo "Migración 005 aplicada en clinica_vida_salud_dev local. Historial vacío; registros existentes conservados.\n";
} catch (Throwable) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "No se aplicó 005. Transacción revertida; revisar requisitos locales sin repetir automáticamente.\n");
    exit(1);
}
