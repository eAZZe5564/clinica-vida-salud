<?php
declare(strict_types=1);
namespace App\Models;

/** Identificación pública estable, derivada de la identidad de PostgreSQL. */
final class Paciente
{
    public static function codigo(int $id): string
    {
        return 'PAC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
