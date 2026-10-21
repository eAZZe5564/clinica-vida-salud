<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\CitaRepository;
use App\Repositories\DisponibilidadRepository;
use App\Repositories\HorarioRepository;
use App\Services\CitaService;
use App\Services\CitaException;
use App\Services\DisponibilidadService;
use App\Services\HorarioService;
use App\Repositories\PacienteRepository;
use App\Services\CatalogoValidacionException;
use App\Models\HorarioAtencion;
use App\Services\ConsultaCitaService;

/** CU13: reservas administrativas. CU14: lectura con alcance por rol. */
final class CitasController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista','medico']);}
    private function exigirReserva(): void { $this->middleware->exigirRoles($this->actor,['administrador','recepcionista']); }
    private function consulta(): ConsultaCitaService
    {
        return new ConsultaCitaService(new CitaRepository($this->pdo),new HorarioRepository($this->pdo),$this->actor);
    }
    public function listar(): void
    {
        $consulta=$this->consulta();$errores=[];$filtros=[];
        foreach(['fecha','desde','hasta','medico_id','especialidad_id','estado','codigo','periodo','pagina'] as $campo){
            $filtros[$campo]=is_string($_GET[$campo]??null)?mb_substr(trim($_GET[$campo]),0,100):'';
        }
        $resultado=['filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1];
        try{$filtros=$consulta->validar($_GET);$resultado=$consulta->listar($filtros);}
        catch(CatalogoValidacionException $e){http_response_code(422);$errores=$e->errores;}
        $sinVinculo=$consulta->propio===0;$opciones=$consulta->opciones();
        $this->pagina('citas/index','Citas médicas',compact('filtros','resultado','errores','sinVinculo')+$opciones);
    }
    public function detalle(int $id): void
    {
        $consulta=$this->consulta();$cita=$consulta->detalle($id);
        // Un ID ajeno y uno inexistente producen el mismo 404, sin revelar su existencia.
        if($cita===null){http_response_code(404);require dirname(__DIR__) . '/Views/errors/404.php';return;}
        $volver='/citas';
        try{$filtros=$consulta->validar($_GET);$volver.='?' . http_build_query(array_filter($filtros,static fn($v)=>$v!==''));}
        catch(CatalogoValidacionException){ /* Un retorno inválido nunca habilita otra agenda ni una URL externa. */ }
        $verMotivo=$consulta->verMotivo;$verCreador=$consulta->verCreador;
        $this->pagina('citas/show','Detalle de cita',compact('cita','volver','verMotivo','verCreador'));
    }
    public function crear(): void
    {
        $this->exigirReserva();
        $this->middleware->exigirCsrf();
        if (($_POST['_formato']??null)==='html') { $this->crearHtml(); return; }
        $disponibilidad=new DisponibilidadService($this->pdo,new DisponibilidadRepository($this->pdo),new HorarioService($this->pdo,new HorarioRepository($this->pdo)));
        $servicio=new CitaService($this->pdo,new CitaRepository($this->pdo),$disponibilidad);
        header('Content-Type: application/json; charset=utf-8');
        try{$resultado=$servicio->crear($_POST,$this->actor['id']);http_response_code(201);echo json_encode(['cita'=>$resultado],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
        catch(CitaException $e){http_response_code($e->estadoHttp);echo json_encode(['errores'=>$e->errores],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
    }
    private function disponibilidad(): DisponibilidadService
    {
        return new DisponibilidadService($this->pdo,new DisponibilidadRepository($this->pdo),new HorarioService($this->pdo,new HorarioRepository($this->pdo)));
    }
    private function servicio(): CitaService { return new CitaService($this->pdo,new CitaRepository($this->pdo),$this->disponibilidad()); }
    private function idFormulario(mixed $valor,string $campo,array &$errores): ?int
    {
        if($valor===null||$valor===''){return null;}
        $id=is_string($valor)||is_int($valor)?filter_var($valor,FILTER_VALIDATE_INT):false;
        if($id===false||$id<=0){$errores[$campo]='Selecciona una opción válida.';return null;}return $id;
    }
    /** Búsqueda y selectores reutilizan repositorios; CU12 calcula los turnos. */
    private function contexto(array $entrada,array $errores=[]): array
    {
        $seleccion=[];
        foreach(['paciente_id','especialidad_id','medico_id','fecha','hora_inicio','motivo','q'] as $campo){
            $valor=$entrada[$campo]??'';
            if(!is_string($valor)&&!is_int($valor)){$errores[$campo]='El valor enviado no es válido.';$valor='';}
            $seleccion[$campo]=mb_substr((string)$valor,0,$campo==='motivo'?1000:100);
        }
        if(!array_key_exists('fecha',$entrada)){$seleccion['fecha']=$this->disponibilidad()->ahora()->format('Y-m-d');}
        $pacienteId=$this->idFormulario($entrada['paciente_id']??'','paciente_id',$errores);
        $especialidadId=$this->idFormulario($entrada['especialidad_id']??'','especialidad_id',$errores);
        $medicoId=$this->idFormulario($entrada['medico_id']??'','medico_id',$errores);
        $pagina=is_string($entrada['pagina_pacientes']??null)?max(1,(int)filter_var($entrada['pagina_pacientes'],FILTER_VALIDATE_INT)):1;
        $pacientesRepo=new PacienteRepository($this->pdo);$repo=new DisponibilidadRepository($this->pdo);
        $pacientes=$pacientesRepo->listar(trim($seleccion['q']),$pagina,true);
        $paciente=$pacienteId===null?null:$pacientesRepo->buscar($pacienteId);
        if($pacienteId!==null&&(!$paciente||!$paciente['activo'])){$errores['paciente_id']='El paciente no existe o está inactivo.';$paciente=null;}
        $especialidades=$repo->especialidades();$especialidad=$especialidadId===null?null:$repo->especialidad($especialidadId);
        if($especialidadId!==null&&(!$especialidad||!$especialidad['activo'])){$errores['especialidad_id']='Selecciona una especialidad activa.';$especialidad=null;}
        $medicos=$especialidad===null?[]:$repo->medicos($especialidadId);
        $medico=$medicoId===null||$especialidad===null?null:$repo->medico($medicoId,$especialidadId);
        if($medicoId!==null&&$especialidad!==null&&(!$medico||!$medico['activo'])){$errores['medico_id']='Selecciona un médico activo de la especialidad elegida.';$medico=null;}
        $disponibles=null;
        if($medico!==null){
            try{$disponibles=$this->disponibilidad()->consultar($medicoId,$seleccion['fecha'],$especialidadId);}
            catch(CatalogoValidacionException $e){$errores=array_merge($errores,$e->errores);}
        }elseif($seleccion['fecha']!==''){
            try{$this->disponibilidad()->validarParametros(['medico_id'=>1,'fecha'=>$seleccion['fecha']]);}
            catch(CatalogoValidacionException $e){if(isset($e->errores['fecha'])){$errores['fecha']=$e->errores['fecha'];}}
        }
        $hoy=$this->disponibilidad()->ahora()->format('Y-m-d');
        $sinHorarios=$medico!==null&&(new HorarioRepository($this->pdo))->listar($medicoId,null,1)['total']===0;
        return compact('seleccion','errores','pacientes','paciente','especialidades','especialidad','medicos','medico','disponibles','hoy','sinHorarios');
    }
    public function formulario(?array $entrada=null,array $errores=[]): void
    {
        $this->exigirReserva();
        $contexto=$this->contexto($entrada??$_GET,$errores);
        if($contexto['errores'] && http_response_code()===200){http_response_code(422);}
        $this->pagina('citas/form','Programar cita',$contexto);
    }
    private function crearHtml(): void
    {
        $accion=$_POST['accion']??'actualizar';
        if(in_array($accion,['actualizar','editar'],true)){$this->formulario($_POST);return;}
        if(!in_array($accion,['previsualizar','confirmar'],true)){http_response_code(422);$this->formulario($_POST,['solicitud'=>'La solicitud no es válida.']);return;}
        try{
            if($accion==='previsualizar'){
                $this->servicio()->validar($_POST);$contexto=$this->contexto($_POST);
                if($contexto['errores']){throw new CitaException($contexto['errores']);}
                $turno=null;
                foreach($contexto['disponibles']['turnos']??[] as $candidato){if((new \DateTimeImmutable($candidato['inicio']))->format('H:i')===$contexto['seleccion']['hora_inicio']){$turno=$candidato;break;}}
                if($turno===null){throw new CitaException(['disponibilidad'=>'Ese horario dejó de estar disponible. Elige otro turno.'],409);}
                $contexto['turno']=$turno;$this->pagina('citas/revisar','Revisar reserva',$contexto);return;
            }
            // El servicio original revalida bajo bloqueo; la revisión no garantiza turno.
            $resultado=$this->servicio()->crear($_POST,$this->actor['id']);
            $_SESSION['citas_confirmadas'][(string)$resultado['id']]=true;
            if(count($_SESSION['citas_confirmadas'])>20){unset($_SESSION['citas_confirmadas'][array_key_first($_SESSION['citas_confirmadas'])]);}
            \redirigir('/citas/confirmacion/' . $resultado['id']);
        }catch(CitaException $e){
            http_response_code($e->estadoHttp);
            if($e->estadoHttp===403){require dirname(__DIR__) . '/Views/errors/403.php';return;}
            $this->formulario($_POST,$e->errores);
        }
    }
    public function confirmacion(int $id): void
    {
        $this->exigirReserva();
        // Recibo limitado a reservas HTML realizadas en esta sesión por el actor.
        $cita=isset($_SESSION['citas_confirmadas'][(string)$id])?(new CitaRepository($this->pdo))->confirmacion($id,$this->actor['id']):null;
        if($cita===null){http_response_code(404);require dirname(__DIR__) . '/Views/errors/404.php';return;}
        $this->pagina('citas/confirmacion','Cita programada',compact('cita'));
    }
}
