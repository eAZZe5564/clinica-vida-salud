<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use RuntimeException;

final class Database
{
    public static function connection(): PDO
    {
        $host = Env::get('DB_HOST');
        $port = Env::get('DB_PORT', '5432');
        $name = Env::get('DB_NAME');
        $user = Env::get('DB_USER');
        $password = Env::get('DB_PASSWORD', '');

        if ($host === null || $name === null || $user === null) {
            throw new RuntimeException('Falta configurar la conexión a PostgreSQL mediante variables de entorno.');
        }

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
