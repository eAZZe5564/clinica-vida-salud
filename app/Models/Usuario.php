<?php

declare(strict_types=1);

namespace App\Models;

final class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $correo,
        public readonly string $rol,
        public readonly bool $activo
    ) {
    }

    public static function desdeFila(array $fila): self
    {
        return new self(
            (int) $fila['id'],
            (string) $fila['correo'],
            (string) $fila['rol_nombre'],
            (bool) $fila['activo']
        );
    }
}
