<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RolRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(): array
    {
        return $this->pdo->query(
            'SELECT id, nombre, descripcion FROM roles ORDER BY id'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, nombre FROM roles WHERE id = :id'
        );
        $consulta->execute(['id' => $id]);
        $rol = $consulta->fetch();

        return $rol ?: null;
    }

    public function buscarPorNombre(string $nombre): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, nombre FROM roles WHERE nombre = :nombre'
        );
        $consulta->execute(['nombre' => $nombre]);
        $rol = $consulta->fetch();

        return $rol ?: null;
    }
}
