<?php
declare(strict_types=1);
namespace App\Services;
use PDOException;

/** Mensajes públicos controlados; nunca contiene SQL ni datos privados. */
final class CitaException extends \DomainException
{
    public function __construct(public readonly array $errores,public readonly int $estadoHttp=422,public readonly ?string $sqlState=null)
    {
        parent::__construct('No se pudo programar la cita.');
    }
    public static function integridad(PDOException $e): self
    {
        $publica=match((string)$e->getCode()){
            '23P01','23505'=>new self(['disponibilidad'=>'El turno ya no está disponible. Consulta otro horario.'],409),
            '23503'=>new self(['datos'=>'Los registros seleccionados ya no son válidos. Revisa la selección.']),
            '23514','23502'=>new self(['datos'=>'La reserva no cumple las condiciones actuales. Revisa fecha, paciente y médico.']),
            '40P01','40001','55P03'=>new self(['disponibilidad'=>'La operación coincidió con otro cambio. Consulta nuevamente la disponibilidad e inténtalo otra vez.'],409),
            default=>new self(['servicio'=>'No se pudo completar la reserva. Inténtalo más tarde.'],503),
        };
        return new self($publica->errores,$publica->estadoHttp,(string)$e->getCode());
    }
}
