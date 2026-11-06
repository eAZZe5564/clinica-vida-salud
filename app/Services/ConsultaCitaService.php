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
        return FiltrosCita::validar($entrada,$this->propio);
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
