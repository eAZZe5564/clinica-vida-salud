<?php
declare(strict_types=1);
namespace App\Services;

/** Solo mensajes fijos; no conserva excepciones PDO, SQL ni valores clínicos. */
final class PerfilClinicoException extends \DomainException
{
    public function __construct(public readonly array $errores,public readonly int $estadoHttp=422)
    {
        parent::__construct('No se pudo completar la operación clínica.');
    }
}
