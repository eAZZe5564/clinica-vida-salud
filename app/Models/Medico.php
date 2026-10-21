<?php
declare(strict_types=1);
namespace App\Models;
final class Medico
{
    /** La identidad de PostgreSQL garantiza unicidad; no depende de contar filas. */
    public static function codigo(int $id): string { return 'MED-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT); }
}
