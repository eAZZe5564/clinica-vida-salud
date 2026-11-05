<?php
declare(strict_types=1);

// Inspección exclusivamente previa a 010: consultas de catálogo/contadores, sin fixtures.
$pdo = null;
$salida = 0;
try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('Solo consola local.');
    }
    require dirname(__DIR__) . '/tests/Cu15Local.php';
    require __DIR__ . '/Cu23Esquema.php';
    $pdo = cu15Conexion(); // Guarda de host loopback, base exacta y PostgreSQL 17.
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec("SET LOCAL statement_timeout = '10s'");
    $pdo->exec(file_get_contents(__DIR__ . '/render/verificar-instalacion.sql'));
    cu23ValidarInstalacion($pdo);
    $nuevos = ['antecedentes_clinicos', 'antecedentes_historial', 'resultados_laboratorio', 'laboratorios_historial'];
    $q = $pdo->prepare("SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
        WHERE n.nspname='public' AND c.relname IN (?, ?, ?, ?)");
    $q->execute($nuevos);
    if ((int)$q->fetchColumn() !== 0) {
        throw new RuntimeException('Ya existen objetos previstos para 010.');
    }
    $tipos = $pdo->query("SELECT c.relname, format_type(a.atttypid,a.atttypmod) AS tipo
        FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid
        JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public'
        AND c.relname IN ('pacientes','usuarios') AND a.attname='id'
        AND a.attnum>0 AND NOT a.attisdropped ORDER BY c.relname")->fetchAll();
    if ($tipos !== [['relname'=>'pacientes','tipo'=>'bigint'], ['relname'=>'usuarios','tipo'=>'bigint']]) {
        throw new RuntimeException('Tipos de referencias incompatibles.');
    }
    echo json_encode([
        'base'=>'clinica_vida_salud_dev',
        'postgresql'=>17,
        'esquema_actual'=>'001-009',
        'tablas'=>12,
        'claves_foraneas'=>20,
        'contrato_cu23_verificado'=>true,
        'objetos_010_ausentes'=>true,
        'tipos_referencias_compatibles'=>true,
        'solo_lectura'=>$pdo->query('SHOW transaction_read_only')->fetchColumn()==='on',
        'migracion_010_ejecutada'=>false,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Inspección previa CU24 no satisfactoria. Sin escrituras; detalle nativo omitido.\n");
    $salida = 1;
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
exit($salida);
