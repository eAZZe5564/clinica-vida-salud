<?php
declare(strict_types=1);
namespace App\Services;
final class CatalogoValidacionException extends \DomainException
{
    public function __construct(public readonly array $errores)
    {
        parent::__construct('Revisa los campos señalados.');
    }
}
