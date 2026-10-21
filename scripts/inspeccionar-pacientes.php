<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/bootstrap.php';
$pdo = App\Config\Database::connection();
if (App\Config\Env::get('DB_HOST') !== '127.0.0.1' || App\Config\Env::get('DB_NAME') !== 'clinica_vida_salud_dev') {
    throw new RuntimeException('Se requiere la base local esperada.');
}
echo json_encode($pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name='pacientes' ORDER BY ordinal_position")->fetchAll(), JSON_PRETTY_PRINT), PHP_EOL;
echo json_encode($pdo->query("SELECT conname, pg_get_constraintdef(oid) AS definicion FROM pg_constraint WHERE conrelid='pacientes'::regclass")->fetchAll(), JSON_PRETTY_PRINT), PHP_EOL;
echo 'Cuentas existentes: ', $pdo->query('SELECT count(*) FROM usuarios')->fetchColumn(), PHP_EOL;
