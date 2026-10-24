<?php
declare(strict_types=1);
namespace App\Services;

/** Política única para altas/cambios y primer administrador; no se aplica al login histórico. */
final class PoliticaContrasena
{
    public const AYUDA='Usa al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo especial. Un espacio no cuenta como símbolo. Máximo 72 bytes.';

    public static function validar(mixed $contrasena): void
    {
        if(!is_string($contrasena)||!mb_check_encoding($contrasena,'UTF-8')){
            throw new ContrasenaValidacionException(['formato'=>'La contraseña debe ser texto UTF-8 válido.']);
        }
        $errores=[];
        if(mb_strlen($contrasena,'UTF-8')<8){$errores['longitud']='Incluye al menos 8 caracteres.';}
        // PASSWORD_DEFAULT usa bcrypt en este entorno: no aceptar truncamiento a 72 bytes.
        if(strlen($contrasena)>72){$errores['maximo']='La contraseña no debe superar 72 bytes.';}
        if(preg_match('/\p{Cc}/u',$contrasena)){$errores['control']='No uses caracteres de control en la contraseña.';}
        if(preg_match('/\p{Lu}/u',$contrasena)!==1){$errores['mayuscula']='Incluye al menos una letra mayúscula.';}
        if(preg_match('/\p{Ll}/u',$contrasena)!==1){$errores['minuscula']='Incluye al menos una letra minúscula.';}
        if(preg_match('/\p{Nd}/u',$contrasena)!==1){$errores['numero']='Incluye al menos un número.';}
        if(preg_match('/[\p{P}\p{S}]/u',$contrasena)!==1){$errores['simbolo']='Incluye al menos un símbolo especial; un espacio no cuenta.';}
        if($errores){throw new ContrasenaValidacionException($errores);}
    }
}
