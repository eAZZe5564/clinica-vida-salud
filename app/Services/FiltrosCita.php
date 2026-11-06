<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use DateTimeImmutable;
/** Validación común de lectura, sin atribuir un rol o alcance al llamador. */
final class FiltrosCita
{
    public static function validar(array $entrada,?int $propio=null): array
    {
        $f=[];$errores=[];
        foreach(['fecha','desde','hasta','medico_id','especialidad_id','estado','codigo','periodo','pagina'] as $campo){
            $v=$entrada[$campo]??'';
            if(!is_string($v)&&!is_int($v)){$errores[$campo]='El valor enviado no es válido.';$v='';}
            if(!mb_check_encoding((string)$v,'UTF-8')||preg_match('/[\x00-\x1F\x7F]/',(string)$v)){$errores[$campo]='El filtro contiene caracteres no válidos.';}
            $f[$campo]=trim((string)$v);
            if(strlen($f[$campo])>100){$errores[$campo]='El filtro es demasiado largo.';}
        }
        foreach(['fecha','desde','hasta'] as $campo){
            if($f[$campo]===''){continue;}
            $d=preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$f[$campo])===1?DateTimeImmutable::createFromFormat('!Y-m-d',$f[$campo],HorarioAtencion::zona()):false;
            if(!$d||$d->format('Y-m-d')!==$f[$campo]||$d->format('Y')==='0000'){$errores[$campo]='Usa una fecha válida con formato AAAA-MM-DD.';}
        }
        if(!isset($errores['desde'])&&!isset($errores['hasta'])&&$f['desde']!==''&&$f['hasta']!==''&&$f['desde']>$f['hasta']){$errores['hasta']='La fecha final debe ser igual o posterior a la inicial.';}
        foreach(['medico_id','especialidad_id','pagina'] as $campo){
            if($f[$campo]===''){continue;}
            $id=filter_var($f[$campo],FILTER_VALIDATE_INT);
            if($id===false||$id<=0){$errores[$campo]='Usa un identificador o página válido.';}
        }
        if($f['estado']!==''&&!array_key_exists($f['estado'],Cita::ESTADOS)){$errores['estado']='Selecciona un estado válido.';}
        $f['periodo']=$f['periodo']===''?'todas':$f['periodo'];
        if(!in_array($f['periodo'],['todas','proximas','historicas'],true)){$errores['periodo']='Selecciona un período válido.';}
        $f['codigo']=strtoupper($f['codigo']);
        if($f['codigo']!==''){
            $valido=preg_match('/^CM-([0-9]{6,19})$/D',$f['codigo'],$m)===1;
            $id=$valido?filter_var(ltrim($m[1],'0'),FILTER_VALIDATE_INT):false;
            if($id===false||$id<=0||Cita::codigo($id)!==$f['codigo']){$errores['codigo']='Usa el código completo de la cita, por ejemplo CM-000001.';}
        }
        if($propio!==null&&$f['medico_id']!==''&&(int)$f['medico_id']!==$propio){$errores['medico_id']='Solo puedes consultar tu propia agenda.';}
        if($errores){throw new CatalogoValidacionException($errores);}return $f;
    }
}
