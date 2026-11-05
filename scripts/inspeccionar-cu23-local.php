<?php

declare(strict_types=1);

// Inspección previa a 009: no instala objetos, no crea fixtures ni consulta datos clínicos.
// Este comando exige todavía el esquema 001–008; no es el verificador posterior de CU23.
$pdo = null;
$salida = 0;
try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('Solo consola local.');
    }
    require dirname(__DIR__) . '/tests/Cu15Local.php';
    $pdo = cu15Conexion();
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec("SET LOCAL statement_timeout = '10s'");
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));

    $tablas = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")
        ->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = ['citas', 'citas_historial', 'especialidades', 'horarios_atencion', 'medicos',
        'medicos_especialidades', 'pacientes', 'roles', 'usuarios'];
    $referencias = (int) $pdo->query("SELECT count(*) FROM pg_constraint
        WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn();
    $asistencias = (bool) $pdo->query("SELECT EXISTS (SELECT 1 FROM pg_constraint
        WHERE conrelid='public.citas'::regclass AND conname='citas_estado_valido'
        AND position('presente' IN pg_get_constraintdef(oid))>0)")->fetchColumn();
    $colisiones = (int) $pdo->query("SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
        WHERE n.nspname='public' AND c.relname IN ('perfiles_clinicos','alergias_paciente','alergias_historial')")
        ->fetchColumn();
    if ($tablas !== $esperadas || $referencias !== 12 || !$asistencias || $colisiones !== 0) {
        throw new RuntimeException('No corresponde al esquema previo completo.');
    }
    $vinculados = (int) $pdo->query("SELECT count(*) FROM medicos m
        JOIN usuarios u ON u.id=m.usuario_id JOIN roles r ON r.id=u.rol_id
        WHERE m.activo AND u.activo AND r.nombre='medico'")->fetchColumn();

    echo json_encode([
        'base' => 'clinica_vida_salud_dev',
        'postgresql' => 17,
        'esquema' => '001-008',
        'tablas' => count($tablas),
        'claves_foraneas' => $referencias,
        'objetos_009_ausentes' => true,
        'medicos_activos_con_cuenta_valida' => $vinculados,
        'solo_lectura' => $pdo->query('SHOW transaction_read_only')->fetchColumn() === 'on',
        'escrituras_ejecutadas' => false,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Inspección previa CU23 no satisfactoria. Sin escrituras; diagnóstico nativo omitido.\n");
    $salida = 1;
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
exit($salida);
