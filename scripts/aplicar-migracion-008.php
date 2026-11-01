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
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_migracion_008'))");
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    $definicion=$pdo->query("SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid='public.citas'::regclass AND conname='citas_estado_valido'")->fetchColumn();
    if (!is_string($definicion) || !str_contains($definicion,'confirmada') || str_contains($definicion,'presente')) {
        throw new RuntimeException('Se requiere 007 instalada y 008 ausente.');
    }
    $pdo->exec('LOCK TABLE citas, citas_historial IN ACCESS EXCLUSIVE MODE');
    $conflictos=(int)$pdo->query("SELECT count(*) FROM citas a JOIN citas b ON a.id<b.id AND a.medico_id=b.medico_id
        AND a.estado<>'cancelada' AND b.estado<>'cancelada'
        AND a.fecha_hora_inicio<b.fecha_hora_inicio+make_interval(mins=>b.duracion_minutos)
        AND b.fecha_hora_inicio<a.fecha_hora_inicio+make_interval(mins=>a.duracion_minutos)")->fetchColumn();
    if ($conflictos!==0) { throw new RuntimeException('Existen intervalos históricos incompatibles.'); }
    $duplicados=(int)$pdo->query("SELECT count(*) FROM (SELECT cita_id,tipo FROM citas_historial WHERE tipo IN ('llegada','atencion','inasistencia') GROUP BY cita_id,tipo HAVING count(*)>1) duplicados")->fetchColumn();
    if ($duplicados!==0) { throw new RuntimeException('Existen eventos duplicados incompatibles.'); }
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/008_citas_asistencia.sql'));
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    $pdo->commit();
    echo "Migración 008 aplicada únicamente en clinica_vida_salud_dev local; nueve tablas y doce FK verificadas.\n";
} catch (Throwable) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "No se aplicó 008. Transacción revertida; no repetir automáticamente.\n");
    exit(1);
}
