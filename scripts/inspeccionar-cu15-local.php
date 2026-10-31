<?php
declare(strict_types=1);
require dirname(__DIR__) . '/tests/Cu15Local.php';
$pdo = cu15Conexion();
$pdo->beginTransaction();
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
try {
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    $result = ['base' => $pdo->query('SELECT current_database()')->fetchColumn(),
        'version' => $pdo->query('SHOW server_version')->fetchColumn(),
        'columnas_citas' => $pdo->query("SELECT column_name,data_type,is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name='citas' ORDER BY ordinal_position")->fetchAll(),
        'restricciones_citas' => $pdo->query("SELECT conname,pg_get_constraintdef(oid) AS definicion FROM pg_constraint WHERE conrelid='public.citas'::regclass ORDER BY conname")->fetchAll(),
        'historial_instalado' => (bool)$pdo->query("SELECT to_regclass('public.citas_historial') IS NOT NULL")->fetchColumn(),
        'tablas_verificadas' => (int)$pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")->fetchColumn(),
        'claves_foraneas_verificadas' => (int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn()];
    foreach ($pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (preg_match('/^[a-z_]+$/D', $table) !== 1) { throw new RuntimeException('Identificador inesperado.'); }
        $result['datos'][$table] = $pdo->query('SELECT count(*) AS filas,md5(COALESCE(string_agg(md5(row_to_json(t)::text),\'\' ORDER BY md5(row_to_json(t)::text)),\'\')) AS huella FROM "' . $table . '" t')->fetch();
    }
    echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) . "\n";
} finally { $pdo->rollBack(); }
