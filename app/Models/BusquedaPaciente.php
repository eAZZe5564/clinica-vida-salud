<?php
declare(strict_types=1);
namespace App\Models;

/** Convenciones de la búsqueda CU05, también reutilizada por los selectores de pacientes. */
final class BusquedaPaciente
{
    public const MAX_CARACTERES = 100;

    public static function normalizar(mixed $entrada): string
    {
        if(!is_string($entrada)||!mb_check_encoding($entrada,'UTF-8')){return '';}
        // Las separaciones se unifican antes de limitar la consulta. No admitir NUL/control oculto.
        $texto=preg_replace('/[\p{Z}\s]+/u',' ',$entrada)??'';
        if(preg_match('/\p{Cc}/u',$texto)){return '';}
        return mb_substr(trim($texto),0,self::MAX_CARACTERES,'UTF-8');
    }

    public static function terminos(string $entrada): array
    {
        $texto=self::normalizar($entrada);
        if($texto===''){return [];}
        // Plegado de vocales españolas, incluidas formas con acento/diéresis combinados; ñ se conserva.
        $texto=strtr(mb_strtolower($texto,'UTF-8'),[
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u',"\u{0301}"=>'',"\u{0308}"=>'',
        ]);
        return array_values(array_unique(explode(' ',$texto)));
    }
}
