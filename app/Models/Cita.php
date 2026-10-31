<?php
declare(strict_types=1);
namespace App\Models;
final class Cita
{
    /** Estados admitidos por citas_estado_valido (migración 007). */
    public const ESTADOS = ['programada'=>'Programada','cancelada'=>'Cancelada','atendida'=>'Atendida','confirmada'=>'Confirmada'];
    /** Identidad PostgreSQL, segura ante concurrencia y con saltos permitidos. */
    public static function codigo(int $id): string { return 'CM-' . str_pad((string)$id,6,'0',STR_PAD_LEFT); }
}
