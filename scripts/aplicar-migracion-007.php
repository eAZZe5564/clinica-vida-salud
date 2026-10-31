<?php
declare(strict_types=1);
// Exclusivo de desarrollo local, nunca ejecutado por el contenedor.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--autorizado') {
    fwrite(STDERR, "Se requiere autorización explícita y el argumento --autorizado.\n");
    exit(2);
}
require dirname(__DIR__) . '/tests/Cu15Local.php';
$pdo = cu15Conexion();
try {
    $pdo->beginTransaction();
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_migracion_007'))");
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    $columnas=(bool)$pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='citas_historial' AND column_name='motivo_cancelacion')")->fetchColumn();
    $instalada=(bool)$pdo->query("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='public.citas'::regclass AND conname='citas_estado_valido' AND position('confirmada' IN pg_get_constraintdef(oid))>0)")->fetchColumn();
    if (!$columnas || $instalada) { throw new RuntimeException('Se requiere 006 instalada y 007 ausente.'); }
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/007_citas_confirmacion.sql'));
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    $pdo->commit();
    echo "Migración 007 aplicada únicamente en clinica_vida_salud_dev local; nueve tablas y doce FK verificadas.\n";
} catch (Throwable) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "No se aplicó 007. Transacción revertida; no repetir automáticamente.\n");
    exit(1);
}
