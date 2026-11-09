<?php

declare(strict_types=1);

namespace App\Config;

// Solo auto_prepend_file de las pruebas. No se incluye en el contexto Docker.
// Usuarios en memoria; no se abre PDO ni existe una base de pruebas real.
final class Database
{
    public static function connection(): \PDO
    {
        self::registro('connection');
        return new MantenimientoFakePDO();
    }

    public static function registro(string $event): void
    {
        $file = getenv('MANTENIMIENTO_FIXTURE_EVENTS');
        if (!is_string($file) || $file === '') { throw new \RuntimeException('Falta fixture aislado.'); }
        file_put_contents($file, $event . "\n", FILE_APPEND | LOCK_EX);
    }
}

final class MantenimientoFakePDO extends \PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new MantenimientoFakeStatement($query);
    }
}

final class MantenimientoFakeStatement extends \PDOStatement
{
    private array|false $row = false;

    public function __construct(private readonly string $query) {}

    public function execute(?array $params = null): bool
    {
        $roles = ['administrador', 'recepcionista', 'medico', 'paciente'];
        $sql = preg_replace('/\s+/', ' ', trim($this->query));
        if (str_starts_with($sql, 'SELECT u.id, u.correo') && str_contains($sql, 'FROM usuarios u JOIN roles r')) {
            foreach ($roles as $index => $role) {
                $id = $index + 1;
                $email = $role . '@example.invalid';
                if (($params['correo'] ?? null) === $email || (int) ($params['id'] ?? 0) === $id) {
                    $this->row = ['id' => $id, 'correo' => $email, 'activo' => true,
                        'rol_id' => $id, 'rol_nombre' => $role,
                        'hash_contrasena' => (string) getenv('MANTENIMIENTO_FIXTURE_HASH')];
                }
            }
            Database::registro('select-user-fixture');
            return true;
        }
        if ($sql === 'UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id') {
            // Efecto simulado: únicamente una marca técnica, sin parámetros ni SQL en logs.
            Database::registro('write-fixture-only');
            return true;
        }
        if ($sql === 'SELECT id,usuario_id,activo FROM pacientes WHERE usuario_id=:u') {
            // Cuenta ficticia sin vinculación: no se consulta ninguna base real.
            Database::registro('select-patient-fixture');
            return true;
        }
        throw new \RuntimeException('Consulta no contemplada por el doble aislado.');
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->row;
    }
}

$root = getenv('MANTENIMIENTO_FIXTURE_ROOT');
if (!is_string($root) || !is_file($root . '/app/Config/mantenimiento-http.php')) {
    throw new \RuntimeException('Solo se admite la exportación aislada.');
}
require_once $root . '/app/Config/mantenimiento-http.php';
