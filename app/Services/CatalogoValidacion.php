<?php
declare(strict_types=1);
namespace App\Services;

/** Reglas de texto e identificadores reutilizadas por los servicios. */
final class CatalogoValidacion
{
    public static function texto(mixed $entrada, string $campo, int $maximo, bool $obligatorio, array &$errores): ?string
    {
        if (!is_string($entrada) || !mb_check_encoding($entrada, 'UTF-8')) {
            $errores[$campo] = 'El texto enviado no es válido.';
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/u', $entrada)) { $errores[$campo] = 'No se permiten caracteres de control.'; }
        $texto = trim(preg_replace('/\s+/u', ' ', $entrada) ?? '');
        if ($obligatorio && $texto === '') { $errores[$campo] = 'Este campo es obligatorio.'; }
        if (mb_strlen($texto, 'UTF-8') > $maximo) { $errores[$campo] = "Máximo $maximo caracteres."; }
        return $texto === '' ? null : $texto;
    }

    public static function registro(string $valor): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', $valor) ?? '', 'UTF-8');
    }

    public static function ids(mixed $entrada, string $campo = 'especialidades'): array
    {
        if (!is_array($entrada) || count($entrada) > 200) {
            throw new CatalogoValidacionException([$campo => 'Selecciona opciones válidas.']);
        }
        $ids = [];
        foreach ($entrada as $valor) {
            $id = is_string($valor) || is_int($valor) ? filter_var($valor, FILTER_VALIDATE_INT) : false;
            if ($id === false || $id <= 0) { throw new CatalogoValidacionException([$campo => 'Selecciona opciones válidas.']); }
            $ids[] = $id;
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new CatalogoValidacionException([$campo => 'No se permiten especialidades duplicadas.']);
        }
        sort($ids);
        return $ids;
    }
}
