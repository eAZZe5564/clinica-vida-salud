<?php
declare(strict_types=1);
namespace App\Services;

/** Solo transporta requisitos incumplidos; nunca incluye la contraseña enviada. */
final class ContrasenaValidacionException extends \DomainException
{
    public function __construct(public readonly array $errores)
    {
        parent::__construct(implode(' ',$errores));
    }
}
