<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\RegistroClinico;
use App\Models\HorarioAtencion;
use App\Repositories\RegistroClinicoRepository;
use App\Repositories\PerfilClinicoRepository;
use Closure;
use DateTimeImmutable;
use PDO;

final class RegistroClinicoService
{
    private AccesoClinicoService $acceso;
    public function __construct(private readonly PDO $pdo,private readonly RegistroClinicoRepository $repo,
        private readonly int $actorId,private readonly ?Closure $reloj=null)
    {
        $this->acceso=new AccesoClinicoService($pdo,new PerfilClinicoRepository($pdo),$actorId,$reloj);
    }
    public static function fecha(mixed $v,string $campo,string $hoy): ?string
    {
        if(!is_string($v)){throw new PerfilClinicoException([$campo=>'Introduce una fecha válida o deja el campo vacío.']);}
        if($v===''){return null;}
        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$v)||substr($v,0,4)==='0000'){
            throw new PerfilClinicoException([$campo=>'Utiliza una fecha válida, sin inventar información desconocida.']);
        }
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,HorarioAtencion::zona());$errores=DateTimeImmutable::getLastErrors();
        if(!$d||($errores!==false&&($errores['warning_count']||$errores['error_count']))||$d->format('Y-m-d')!==$v||$v>$hoy){
            throw new PerfilClinicoException([$campo=>'La fecha debe ser real y no futura.']);
        }
        return $v;
    }
    public static function decimal(mixed $v): ?string
    {
        if($v===''){return null;}
        if(!is_string($v)||!preg_match('/^-?(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D',$v)){
            throw new PerfilClinicoException(['valor'=>'Introduce un decimal con punto, hasta doce cifras enteras y seis decimales, o deja el campo vacío.']);
        }
        // Normalizar sin float ni redondeo para comparar versiones equivalentes.
        if(str_contains($v,'.')){$v=rtrim(rtrim($v,'0'),'.');}
        return $v==='-0'?'0':$v;
    }
    public function consultar(int $paciente,string $tipo,?int $id=null): array
    {
        RegistroClinico::tipo($tipo);
        return $this->acceso->operar($paciente,false,function(array $p)use($paciente,$tipo,$id):array{
            $r=$id===null?null:$this->repo->registro($paciente,$tipo,$id);
            if($id!==null&&$r===null){throw new PerfilClinicoException(['registro'=>'Recurso no disponible.'],404);}
            return ['paciente'=>$p,'registros'=>$this->repo->listar($paciente,$tipo),'registro'=>$r,
                'revisiones'=>$id===null?[]:$this->repo->revisiones($paciente,$tipo,$id)];
        });
    }
    public function guardar(int $paciente,string $tipo,array $e,?int $id=null): int
    {
        RegistroClinico::tipo($tipo);
        return $this->acceso->operar($paciente,true,function()use($paciente,$tipo,$e,$id):int{
            $previo=$id===null?null:$this->repo->registro($paciente,$tipo,$id);
            if($id!==null&&$previo===null){throw new PerfilClinicoException(['registro'=>'Recurso no disponible.'],404);}
            if(($e['confirmar']??null)!=='1'){throw new PerfilClinicoException(['confirmar'=>'Confirma expresamente antes de guardar.']);}
            if($previo){
                $v=$e['version']??null;
                if((!is_int($v)&&!is_string($v))||filter_var($v,FILTER_VALIDATE_INT)===false||(int)$v!==(int)$previo['version']){
                    throw new PerfilClinicoException(['version'=>'El registro cambió. Recarga antes de guardar.'],409);
                }
            }
            $ahora=$this->reloj===null?new DateTimeImmutable($this->pdo->query('SELECT clock_timestamp()')->fetchColumn()):($this->reloj)();
            $hoy=$ahora->setTimezone(HorarioAtencion::zona())->format('Y-m-d');$d=[];
            $textos=$tipo==='antecedentes'?['categoria'=>20,'descripcion'=>1000,'observaciones'=>500]
                :['estudio'=>120,'resultado_texto'=>2000,'unidad'=>40,'rango_referencia'=>250,'laboratorio'=>160,'observaciones'=>500];
            foreach($textos as $c=>$max){$d[$c]=PerfilClinicoService::texto($e[$c]??'',$c,$max,in_array($c,['categoria','descripcion','estudio','resultado_texto'],true));}
            if($tipo==='antecedentes'){
                if(!isset(RegistroClinico::CATEGORIAS[$d['categoria']])){throw new PerfilClinicoException(['categoria'=>'Selecciona una categoría disponible.']);}
                $d['fecha_evento']=self::fecha($e['fecha_evento']??'','fecha_evento',$hoy);
                $aproximada=$e['fecha_aproximada']??'';
                if(!is_string($aproximada)||($d['fecha_evento']===null?$aproximada!=='':!in_array($aproximada,['0','1'],true))){
                    throw new PerfilClinicoException(['fecha_aproximada'=>'Indica si la fecha conocida es aproximada; si no hay fecha, deja ambos campos vacíos.']);
                }
                $d['fecha_aproximada']=$d['fecha_evento']===null?null:($aproximada==='1'?'true':'false');
            }else{
                foreach(['fecha_muestra','fecha_resultado'] as $c){$d[$c]=self::fecha($e[$c]??'',$c,$hoy);}
                if($d['fecha_muestra']!==null&&$d['fecha_resultado']!==null&&$d['fecha_muestra']>$d['fecha_resultado']){
                    throw new PerfilClinicoException(['fecha_resultado'=>'El resultado no puede tener fecha anterior a la muestra.']);
                }
                $d['valor']=self::decimal($e['valor']??'');
                if($d['unidad']!==null&&$d['valor']===null){throw new PerfilClinicoException(['unidad'=>'Una unidad numérica requiere un valor; no conviertas un resultado cualitativo en número.']);}
            }
            $d['estado']=$previo===null?'activo':PerfilClinicoService::texto($e['estado']??'','estado',10,true);
            if(!in_array($d['estado'],['activo','inactivo'],true)){throw new PerfilClinicoException(['estado'=>'Selecciona un estado disponible.']);}
            $motivo=$previo===null?null:PerfilClinicoService::texto($e['motivo_cambio']??'','motivo_cambio',250,true);
            if($previo){
                $igual=true;foreach($d as $c=>$v){$ant=$previo[$c];if($c==='fecha_aproximada'){$ant=$ant===null?null:($ant?'true':'false');}if($c==='valor'){$ant=$ant===null?null:self::decimal((string)$ant);}if($ant!==$v){$igual=false;break;}}
                if($igual){throw new PerfilClinicoException(['cambios'=>'No hay cambios que registrar.'],409);}
            }
            $evento=$previo===null?'registro':($previo['estado']===$d['estado']?'rectificacion':($d['estado']==='activo'?'reactivacion':'inactivacion'));
            $nuevo=$this->repo->guardar($paciente,$tipo,$d,$this->actorId,$previo);
            $this->repo->revisar($paciente,$tipo,$nuevo,$evento,$motivo,$this->actorId);
            return $nuevo;
        });
    }
}
