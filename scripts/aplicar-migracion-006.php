<?php
declare(strict_types=1);
// Solo desarrollo local y tras autorización; nunca se invoca al arrancar la aplicación.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--autorizado') {
    fwrite(STDERR, "Se requiere autorización explícita y el argumento --autorizado.\n");
    exit(2);
}
require dirname(__DIR__) . '/tests/Cu15Local.php';
$pdo = cu15Conexion();
try {
    $pdo->beginTransaction();
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_migracion_006'))");
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    if (!$pdo->query("SELECT to_regclass('public.citas_historial')")->fetchColumn()
        || $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='citas_historial' AND column_name='motivo_cancelacion')")->fetchColumn()) {
        throw new RuntimeException('Se requiere 005 instalada y 006 ausente.');
    }
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/006_citas_cancelacion.sql'));
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    $pdo->commit();
    echo "Migración 006 aplicada únicamente en clinica_vida_salud_dev local; nueve tablas y doce FK verificadas.\n";
} catch (Throwable) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "No se aplicó 006. Transacción revertida; no repetir automáticamente.\n");
    exit(1);
}
