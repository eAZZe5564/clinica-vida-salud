<?php
declare(strict_types=1);

// Guarda previa a cualquier conexión: estas herramientas nunca admiten Render.
require_once dirname(__DIR__) . '/app/Config/Env.php';
App\Config\Env::load(dirname(__DIR__) . '/.env');
if (!in_array(App\Config\Env::get('DB_HOST'), ['127.0.0.1', 'localhost', '::1'], true)
    || App\Config\Env::get('DB_NAME') !== 'clinica_vida_salud_dev') {
    throw new RuntimeException('CU15: solo se autoriza PostgreSQL local clinica_vida_salud_dev.');
}
require_once dirname(__DIR__) . '/app/Config/bootstrap.php';

function cu15Conexion(): PDO
{
    $pdo = App\Config\Database::connection();
    $identity=$pdo->query("SELECT current_database() AS base,host(inet_server_addr()) AS servidor,current_setting('server_version_num')::int AS version")->fetch();
    if ($identity['base'] !== 'clinica_vida_salud_dev'
        || !in_array($identity['servidor'], ['127.0.0.1','::1'],true)
        || (int)$identity['version']<170000 || (int)$identity['version']>=180000) {
        throw new RuntimeException('CU15: identidad de base no autorizada.');
    }
    return $pdo;
}
