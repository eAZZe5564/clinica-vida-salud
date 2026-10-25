<?php
declare(strict_types=1);

/** Guardas exclusivas de la consola remota. No carga ni modifica .env. */
final class RenderDestino
{
    public static function validar(array $config): array
    {
        foreach (['host','base','usuario','password','ca','host_confirmado','base_confirmada'] as $campo) {
            if (!isset($config[$campo]) || !is_string($config[$campo]) || $config[$campo] === '' || preg_match('/[\x00-\x1f\x7f]/', $config[$campo])) {
                throw new DomainException('Configuración remota incompleta o inválida.');
            }
        }
        if (preg_match('/^dpg-[a-z0-9-]+\.[a-z0-9-]+-postgres\.render\.com$/D', $config['host']) !== 1
            || $config['host'] !== $config['host_confirmado']
            || preg_match('/^clinica_vida_salud(?:_[a-z0-9]+)*$/D', $config['base']) !== 1
            || strlen($config['base']) > 63 || $config['base'] === 'clinica_vida_salud_dev'
            || $config['base'] !== $config['base_confirmada']
            || preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $config['usuario']) !== 1
            || ($config['puerto'] ?? null) !== 5432 || !is_file($config['ca']) || !is_readable($config['ca'])) {
            throw new DomainException('Destino no autorizado: verifica host externo Render, base exclusiva de la clínica, usuario, puerto y CA.');
        }
        return $config;
    }

    public static function conectar(array $config): PDO
    {
        $config = self::validar($config);
        // Proceso CLI separado: estas variables nunca alteran el entorno ni .env de PowerShell.
        foreach (['DB_HOST'=>$config['host'],'DB_PORT'=>'5432','DB_NAME'=>$config['base'],
                  'DB_USER'=>$config['usuario'],'DB_PASSWORD'=>$config['password'],
                  'PGSSLMODE'=>'verify-full','PGSSLROOTCERT'=>$config['ca'],'PGCONNECT_TIMEOUT'=>'10',
                  'PGOPTIONS'=>'-c search_path=public','PGSERVICE'=>'','PGSERVICEFILE'=>'','PGHOSTADDR'=>''] as $clave=>$valor) {
            putenv($clave . '=' . $valor);
            $_ENV[$clave] = $valor;
        }
        return App\Config\Database::connection();
    }

    public static function comprobar(PDO $pdo, array $config): void
    {
        $destino = $pdo->query("SELECT current_database() AS base, current_user AS usuario,
            current_setting('server_version_num')::int AS version,
            (SELECT ssl FROM pg_stat_ssl WHERE pid=pg_backend_pid()) AS tls")->fetch();
        if (!$destino || $destino['base'] !== $config['base'] || $destino['usuario'] !== $config['usuario']
            || (int)$destino['version'] < 170000 || (int)$destino['version'] >= 180000
            || !in_array($destino['tls'], [true, 1, '1', 't'], true)) {
            throw new DomainException('La identidad, versión o TLS de la base no coincide; no se creó ninguna cuenta.');
        }
    }
}
