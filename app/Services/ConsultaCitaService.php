<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\CitaRepository;
use App\Repositories\HorarioRepository;
use DateTimeImmutable;

/** CU14: validaciones de lectura y alcance derivado exclusivamente del actor autenticado. */
final class ConsultaCitaService
{
    public readonly ?int $propio;
    public readonly bool $verMotivo;
    public readonly bool $verCreador;
    public function __construct(private readonly CitaRepository $repo, private readonly HorarioRepository $horarios, array $actor)
    {
        if(!in_array($actor['rol']??null,['administrador','recepcionista','medico'],true)){
            throw new CitaException(['permisos'=>'No tienes autorización para consultar citas.'],403);
        }
        // Un médico sin vínculo usa 0: jamás confundirlo con NULL (alcance administrativo).
        $this->propio=$actor['rol']==='medico'?($horarios->vinculado((int)$actor['id'])??0):null;
        $this->verMotivo=in_array($actor['rol'],['administrador','recepcionista'],true);
        $this->verCreador=$actor['rol']==='administrador';
    }
    public function validar(array $entrada): array
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
        if($this->propio!==null&&$f['medico_id']!==''&&(int)$f['medico_id']!==$this->propio){$errores['medico_id']='Solo puedes consultar tu propia agenda.';}
        if($errores){throw new CatalogoValidacionException($errores);}return $f;
    }
    private function presentar(array $cita): array
    {
        $inicio=(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        $cita['codigo']=Cita::codigo((int)$cita['id']);$cita['fecha']=$inicio->format('d/m/Y');
        $cita['hora_inicio']=$inicio->format('H:i');$cita['hora_fin']=$inicio->modify('+' . (int)$cita['duracion_minutos'] . ' minutes')->format('H:i');
        if(isset($cita['creada_en'])){$cita['registro_local']=(new DateTimeImmutable($cita['creada_en']))->setTimezone(HorarioAtencion::zona())->format('d/m/Y H:i');}
        return $cita;
    }
    public function listar(array $entrada): array
    {
        $f=$this->validar($entrada);$sql=['periodo'=>$f['periodo'],'ahora'=>(new DateTimeImmutable('now',HorarioAtencion::zona()))->format('c')];
        foreach(['medico_id','especialidad_id','estado'] as $campo){if($f[$campo]!==''){$sql[$campo]=$campo==='estado'?$f[$campo]:(int)$f[$campo];}}
        if($f['codigo']!==''){$sql['id']=(int)substr($f['codigo'],3);}
        // Fecha exacta y rango se intersectan; límites superiores exclusivos incluyen todo el día final.
        $desde=array_filter([$f['fecha'],$f['desde']]);$hasta=array_filter([$f['fecha'],$f['hasta']]);
        if($desde){$sql['fecha_desde']=(new DateTimeImmutable(max($desde),HorarioAtencion::zona()))->format('c');}
        if($hasta){$sql['fecha_hasta']=(new DateTimeImmutable(min($hasta),HorarioAtencion::zona()))->modify('+1 day')->format('c');}
        $resultado=$this->repo->listar($sql,$this->propio,$f['pagina']===''?1:(int)$f['pagina']);
        $resultado['filas']=array_map($this->presentar(...),$resultado['filas']);return $resultado;
    }
    public function detalle(int $id): ?array
    {
        $cita=$this->repo->detalle($id,$this->propio,$this->verMotivo,$this->verCreador);
        return $cita===null?null:$this->presentar($cita);
    }
    public function opciones(): array
    {
        return ['medicos'=>$this->horarios->medicos($this->propio),'especialidades'=>$this->repo->especialidadesConsulta($this->propio)];
    }
}
