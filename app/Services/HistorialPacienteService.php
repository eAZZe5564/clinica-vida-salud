<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\BusquedaPaciente;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Paciente;
use App\Repositories\HistorialPacienteRepository;
use DateTimeImmutable;
use DateTimeZone;

/** Reutiliza el alcance y validaciones CU14 sin ampliar permisos de CU04 o del paciente. */
final class HistorialPacienteService
{
    public const EVENTOS=['reprogramacion'=>'Reprogramación','cancelacion'=>'Cancelación','confirmacion'=>'Confirmación',
        'llegada'=>'Llegada registrada','atencion'=>'Atención completada','inasistencia'=>'Inasistencia'];
    public function __construct(private readonly HistorialPacienteRepository $repo,private readonly ConsultaCitaService $consulta) {}
    public function sinVinculo(): bool{return $this->consulta->propio===0;}
    public function buscar(array $entrada): array
    {
        $valor=$entrada['q']??'';
        if(!is_string($valor)||!mb_check_encoding($valor,'UTF-8')||mb_strlen($valor)>100||preg_match('/[\x00-\x1F\x7F]/',$valor)){
            throw new CatalogoValidacionException(['q'=>'Usa una búsqueda válida de hasta 100 caracteres.']);
        }
        $q=BusquedaPaciente::normalizar($valor);$pagina=$this->pagina($entrada['pagina']??'1');
        return ['busqueda'=>$q,'sinVinculo'=>$this->sinVinculo(),'buscarCI'=>$this->consulta->propio===null,
            'resultado'=>$this->repo->buscar($q,$pagina,$this->consulta->propio)];
    }
    private function pagina(mixed $valor): int
    {
        $id=is_string($valor)||is_int($valor)?filter_var($valor,FILTER_VALIDATE_INT):false;
        if($id===false||$id<=0){throw new CatalogoValidacionException(['pagina'=>'Usa una página válida.']);}return $id;
    }
    private function paciente(int $id): array
    {
        $p=$this->repo->paciente($id,$this->consulta->propio);
        if($p===null){throw new CitaException(['paciente'=>'El paciente no existe.'],404);}
        $p['codigo']=Paciente::codigo((int)$p['id']);return $p;
    }
    private function presentar(array $c): array
    {
        $inicio=(new DateTimeImmutable($c['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        $c['codigo']=Cita::codigo((int)$c['id']);$c['inicio_local']=$inicio->format('d/m/Y H:i');
        $c['fin_local']=$inicio->modify('+'.(int)$c['duracion_minutos'].' minutes')->format('d/m/Y H:i');return $c;
    }
    public function consultar(int $id,array $entrada): array
    {
        $paciente=$this->paciente($id);
        $permitidos=['desde','hasta','estado','medico_id','especialidad_id','periodo','pagina'];
        $f=$this->consulta->validar(array_intersect_key($entrada,array_flip($permitidos)));
        $utc=new DateTimeZone('UTC');
        foreach(['desde'=>'limite_desde','hasta'=>'limite_hasta'] as $campo=>$limite){
            if($f[$campo]!==''){$fecha=new DateTimeImmutable($f[$campo],HorarioAtencion::zona());if($campo==='hasta'){$fecha=$fecha->modify('+1 day');}$f[$limite]=$fecha->setTimezone($utc)->format('Y-m-d H:i:sP');}
        }
        $f['ahora']=(new DateTimeImmutable('now',HorarioAtencion::zona()))->format('c');
        $resultado=$this->repo->listar($id,$this->consulta->propio,$f);
        $resultado['filas']=array_map($this->presentar(...),$resultado['filas']);
        return compact('paciente','f','resultado')+$this->repo->opciones($id,$this->consulta->propio)+['seleccionarMedico'=>$this->consulta->propio===null];
    }
    public function eventos(int $pacienteId,int $citaId,array $entrada): array
    {
        $paciente=$this->paciente($pacienteId);$cita=$this->repo->cita($pacienteId,$citaId,$this->consulta->propio);
        if($cita===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
        $cita=$this->presentar($cita);$verResponsable=$this->consulta->verCreador;
        $eventos=$this->repo->eventos($pacienteId,$citaId,$this->consulta->propio,$this->pagina($entrada['pagina']??'1'),$verResponsable);
        foreach($eventos['filas'] as &$e){
            foreach(['inicio_anterior','inicio_nuevo','cambiado_en'] as $campo){$e[$campo.'_local']=(new DateTimeImmutable($e[$campo]))->setTimezone(HorarioAtencion::zona())->format('d/m/Y H:i:s');}
        }unset($e);
        return compact('paciente','cita','eventos','verResponsable');
    }
}
