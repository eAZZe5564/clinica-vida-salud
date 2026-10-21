<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Env;
use DateTimeZone;

final class HorarioAtencion
{
    public const DIAS = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
    // Coincide con citas_duracion_ciclo1 de la migración 001.
    public const DURACION_MINUTOS = 30;
    public static function zona(): DateTimeZone { return new DateTimeZone(Env::get('APP_TIMEZONE','UTC')); }
}
