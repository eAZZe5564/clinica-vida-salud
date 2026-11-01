<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\AgendaRepository;
use App\Repositories\HorarioRepository;
use DateTimeImmutable;
use DateTimeZone;

/** CU19 no reserva ni modifica citas; los huecos son descriptivos, no disponibilidad CU12. */
final class AgendaService
{
    public const FILTROS=['operativa'=>'Sin canceladas','todas'=>'Todos los estados']+Cita::ESTADOS;
    public readonly ?int $propio;

    public function __construct(private readonly AgendaRepository $repo,private readonly HorarioRepository $horarios,array $actor)
    {
        if(!in_array($actor['rol']??null,['administrador','recepcionista','medico'],true)){
            throw new CitaException(['permisos'=>'No tienes autorización para consultar agendas.'],403);
        }
        $this->propio=$actor['rol']==='medico'?($horarios->vinculado((int)$actor['id'])??0):null;
    }

    public function validar(array $entrada): array
    {
        $f=[];$errores=[];
        foreach(['fecha','medico_id','estado'] as $campo){
            $v=$entrada[$campo]??'';
            if(!is_string($v)&&!is_int($v)){$errores[$campo]='El filtro debe ser un valor simple.';$v='';}
            $v=(string)$v;
            if(strlen($v)>40||!mb_check_encoding($v,'UTF-8')||preg_match('/[\x00-\x1F\x7F]/',$v)){$errores[$campo]='El filtro no es válido.';}
            $f[$campo]=trim($v);
        }
        if($f['fecha']===''){$f['fecha']=(new DateTimeImmutable('now',HorarioAtencion::zona()))->format('Y-m-d');}
        if(self::fecha($f['fecha'])===null){$errores['fecha']='Usa una fecha válida con formato AAAA-MM-DD.';}
        if($f['medico_id']!==''){
            $id=filter_var($f['medico_id'],FILTER_VALIDATE_INT);
            if($id===false||$id<=0){$errores['medico_id']='Selecciona un médico válido.';}
            elseif($this->propio!==null&&$id!==$this->propio){
                throw new CitaException(['permisos'=>'Solo puedes consultar tu propia agenda.'],403);
            }
        }
        $f['estado']=$f['estado']===''?'operativa':$f['estado'];
        if(!array_key_exists($f['estado'],self::FILTROS)){$errores['estado']='Selecciona un estado válido.';}
        if($errores){throw new CatalogoValidacionException($errores);}
        return $f;
    }

    private static function fecha(string $valor): ?DateTimeImmutable
    {
        if(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$valor)!==1||substr($valor,0,4)==='0000'){return null;}
        $fecha=DateTimeImmutable::createFromFormat('!Y-m-d',$valor,HorarioAtencion::zona());
        return $fecha&&$fecha->format('Y-m-d')===$valor?$fecha:null;
    }

    public static function url(string $vista,string $fecha,?int $medico,string $estado): string
    {
        return '/agenda/'.$vista.'?'.http_build_query(['fecha'=>$fecha,'medico_id'=>$medico===null?'':$medico,'estado'=>$estado]);
    }

    public function consultar(string $vista,array $entrada): array
    {
        if(!in_array($vista,['diaria','semanal'],true)){throw new CitaException(['vista'=>'La vista no existe.'],404);}
        $f=$this->validar($entrada);$fecha=self::fecha($f['fecha']);
        $desde=$vista==='semanal'?$fecha->modify('-'.((int)$fecha->format('N')-1).' days'):$fecha;
        $hasta=$desde->modify($vista==='semanal'?'+7 days':'+1 day');
        // No generar enlaces con años fuera del formato aceptado por el formulario.
        $medicoId=$this->propio??($f['medico_id']===''?null:(int)$f['medico_id']);
        $medicos=$this->horarios->medicos($this->propio);$medico=null;
        if($medicoId!==null&&$medicoId!==0){
            foreach($medicos as $m){if((int)$m['id']===$medicoId){$medico=$m;break;}}
            if($medico===null){throw new CitaException(['medico'=>'El médico no existe.'],404);}
        }
        $especialidades=$medico?$this->repo->especialidades($medicoId):[];
        // Un profesional inactivo conserva sus citas, pero no atención operativa vigente.
        $horarios=$medico&&$medico['activo']?$this->repo->horarios($medicoId):[];
        $utc=new DateTimeZone('UTC');
        $citas=$medico?$this->repo->citas($medicoId,$desde->setTimezone($utc)->format('Y-m-d H:i:sP'),$hasta->setTimezone($utc)->format('Y-m-d H:i:sP')):[];
        foreach($citas as &$c){
            $c['inicio']=(new DateTimeImmutable($c['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
            $c['fin']=$c['inicio']->modify('+'.(int)$c['duracion_minutos'].' minutes');
            $c['codigo']=Cita::codigo((int)$c['id']);
        }unset($c);
        $dias=[];
        for($dia=$desde;$dia<$hasta;$dia=$dia->modify('+1 day')){
            $dias[]=$this->dia($dia,$horarios,$citas,$f['estado']);
        }
        $paso=$vista==='semanal'?7:1;$navegacion=[];
        foreach(['anterior'=>$desde->modify('-'.$paso.' days'),'siguiente'=>$desde->modify('+'.$paso.' days'),
            'hoy'=>new DateTimeImmutable('today',HorarioAtencion::zona())] as $key=>$destino){
            $navegacion[$key]=self::fecha($destino->format('Y-m-d'))===null?null:self::url($vista,$destino->format('Y-m-d'),$medicoId,$f['estado']);
        }
        return compact('vista','f','desde','hasta','medicoId','medico','medicos','especialidades','dias','navegacion')+['sinVinculo'=>$this->propio===0];
    }

    private function dia(DateTimeImmutable $dia,array $horarios,array $citas,string $estado): array
    {
        $finDia=$dia->modify('+1 day');$franjas=[];
        foreach($horarios as $h){if((int)$h['dia_semana']===(int)$dia->format('N')){
            $franjas[]=['inicio'=>$dia->setTime((int)substr($h['hora_inicio'],0,2),(int)substr($h['hora_inicio'],3,2)),
                'fin'=>$dia->setTime((int)substr($h['hora_fin'],0,2),(int)substr($h['hora_fin'],3,2))];
        }}
        $eventos=[];$numero=0;
        $presentes=array_values(array_filter($citas,static fn($c)=>$c['inicio']<$finDia&&$c['fin']>$dia));
        foreach($presentes as $c){
            if($c['inicio']<$dia||($estado==='operativa'&&$c['estado']==='cancelada')||($estado!=='operativa'&&$estado!=='todas'&&$estado!==$c['estado'])){continue;}
            $cubierta=false;
            foreach($franjas as $h){if($h['inicio']<=$c['inicio']&&$h['fin']>=$c['fin']){$cubierta=true;break;}}
            $eventos[]=['tipo'=>'cita','inicio'=>$c['inicio'],'fin'=>$c['fin'],'cita'=>$c,'cubierta'=>$cubierta];++$numero;
        }
        $cursor=$dia;
        foreach($franjas as $h){
            if($cursor<$h['inicio']){$eventos[]=['tipo'=>'fuera','inicio'=>$cursor,'fin'=>$h['inicio']];}
            for($turno=$h['inicio'];$turno<$h['fin'];$turno=$turno->modify('+30 minutes')){
                $fin=$turno->modify('+30 minutes');$ocupadas=array_filter($presentes,static fn($c)=>$c['estado']!=='cancelada'&&$c['inicio']<$fin&&$c['fin']>$turno);
                if(!$ocupadas){$eventos[]=['tipo'=>'vacio','inicio'=>$turno,'fin'=>$fin];}
                elseif(!array_filter($eventos,static fn($ev)=>$ev['tipo']==='cita'&&$ev['cita']['estado']!=='cancelada'&&$ev['inicio']<$fin&&$ev['fin']>$turno)){
                    $eventos[]=['tipo'=>'oculto','inicio'=>$turno,'fin'=>$fin];
                }
            }
            $cursor=$h['fin'];
        }
        if($cursor<$finDia){$eventos[]=['tipo'=>'fuera','inicio'=>$cursor,'fin'=>$finDia];}
        usort($eventos,static fn($a,$b)=>($a['inicio']<=>$b['inicio'])?:((int)($a['cita']['id']??0)<=>(int)($b['cita']['id']??0)));
        return ['fecha'=>$dia->format('Y-m-d'),'titulo'=>HorarioAtencion::DIAS[(int)$dia->format('N')],
            'rotulo'=>$dia->format('d/m/Y'),'franjas'=>$franjas,'eventos'=>$eventos,'numero'=>$numero];
    }
}
