<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UsuarioRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscarPorCorreo(string $correo): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT u.id, u.correo, u.hash_contrasena, u.activo, u.rol_id,
                    r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE LOWER(u.correo) = LOWER(:correo)
             LIMIT 1'
        );
        $consulta->execute(['correo' => $correo]);
        $usuario = $consulta->fetch();

        return $usuario ?: null;
    }

    public function buscarActivoPorId(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT u.id, u.correo, u.activo, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id AND u.activo = TRUE'
        );
        $consulta->execute(['id' => $id]);
        $usuario = $consulta->fetch();

        return $usuario ?: null;
    }

    public function listar(string $busqueda = ''): array
    {
        $sql = 'SELECT u.id, u.correo, u.activo, u.creado_en, u.actualizado_en,
                       u.rol_id, r.nombre AS rol_nombre
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id';
        $parametros = [];
        if ($busqueda !== '') {
            $sql .= ' WHERE u.correo ILIKE :patron_correo OR r.nombre ILIKE :patron_rol';
            $patron = '%' . strtr($busqueda, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $parametros = ['patron_correo' => $patron, 'patron_rol' => $patron];
        }
        $sql .= ' ORDER BY u.id DESC LIMIT 100';

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function buscarPorId(int $id, bool $bloquear = false): ?array
    {
        $sql = 'SELECT u.id, u.correo, u.activo, u.rol_id, r.nombre AS rol_nombre
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                WHERE u.id = :id';
        if ($bloquear) {
            $sql .= ' FOR UPDATE OF u';
        }

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute(['id' => $id]);
        $usuario = $consulta->fetch();

        return $usuario ?: null;
    }

    public function crear(string $correo, string $hash, int $rolId): int
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO usuarios (correo, hash_contrasena, rol_id)
             VALUES (:correo, :hash, :rol_id)
             RETURNING id'
        );
        $consulta->execute([
            'correo' => $correo,
            'hash' => $hash,
            'rol_id' => $rolId,
        ]);

        return (int) $consulta->fetchColumn();
    }

    public function actualizar(
        int $id,
        string $correo,
        int $rolId,
        ?string $hash = null
    ): void {
        if ($hash === null) {
            $sql = 'UPDATE usuarios
                    SET correo = :correo, rol_id = :rol_id, actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id';
            $parametros = ['correo' => $correo, 'rol_id' => $rolId, 'id' => $id];
        } else {
            $sql = 'UPDATE usuarios
                    SET correo = :correo, rol_id = :rol_id, hash_contrasena = :hash,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id';
            $parametros = [
                'correo' => $correo,
                'rol_id' => $rolId,
                'hash' => $hash,
                'id' => $id,
            ];
        }

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE usuarios
             SET activo = :activo, actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $consulta->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    public function actualizarHash(int $id, string $hash): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE usuarios
             SET hash_contrasena = :hash, actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $consulta->execute(['hash' => $hash, 'id' => $id]);
    }

    public function registrarAcceso(int $id): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $consulta->execute(['id' => $id]);
    }

    public function contarAdministradoresActivos(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*)
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre = 'administrador' AND u.activo = TRUE"
        )->fetchColumn();
    }

    public function contarUsuarios(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }

    public function bloquearCambiosAdministrativos(): void
    {
        // Serializa operaciones concurrentes que podrían desactivar/degradar al último administrador.
        $this->pdo->query(
            "SELECT pg_advisory_xact_lock(hashtext('clinica_vida_salud_admin_users'))"
        );
    }
}
