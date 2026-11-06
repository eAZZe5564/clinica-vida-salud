<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\{PortalPacienteRepository,HistorialPacienteRepository,CitaRepository,DisponibilidadRepository,HorarioRepository};
use App\Models\{Cita,HorarioAtencion};
use DateTimeImmutable;
use PDO;

final class PortalPacienteService
{
    private PortalPacienteRepository $identidades;
    private HistorialPacienteRepository $historial;
    private CitaRepository $citas;
    private DisponibilidadService $disponibilidad;
    private ReprogramacionService $reprogramaciones;
    private CancelacionService $cancelaciones;
    public function __construct(private readonly PDO $pdo,private readonly int $actorId)
    {
        $this->identidades=new PortalPacienteRepository($pdo);$this->historial=new HistorialPacienteRepository($pdo);$this->citas=new CitaRepository($pdo);
        $this->disponibilidad=new DisponibilidadService($pdo,new DisponibilidadRepository($pdo),new HorarioService($pdo,new HorarioRepository($pdo)));
        $this->reprogramaciones=new ReprogramacionService($pdo,$this->citas,$this->disponibilidad);$this->cancelaciones=new CancelacionService($pdo,$this->citas);
    }
    public static function parametros(array $e): void
    {
        foreach(['paciente_id','usuario_id','actor_id','rol','documento_identidad','ci','nombres','apellidos','correo','telefono'] as $k){if(array_key_exists($k,$e)){throw new CitaException(['identidad'=>'La identidad del portal no puede elegirse mediante parámetros.']);}}
    }
    private function identidad(bool $bloquear=false): array
    {
        $actor=$this->identidades->actor($this->actorId);
        if(!$actor||!$actor['activo']||$actor['rol']!=='paciente'){throw new CitaException(['permisos'=>'Esta operación es exclusiva de cuentas Paciente activas.'],403);}
        $p=$this->identidades->paciente($this->actorId,$bloquear);
        $actor=$this->identidades->actor($this->actorId,$bloquear);
        if(!$actor||!$actor['activo']||$actor['rol']!=='paciente'){throw new CitaException(['permisos'=>'No tienes acceso al portal.'],403);}
        if(!$p||!$p['activo']){throw new CitaException(['vinculo'=>'Tu cuenta no tiene una vinculación activa verificada. Solicita atención administrativa; no se muestran citas.'],403);}
        return $p;
    }
    private function leer(\Closure $accion): mixed
    {
        $propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            elseif($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'||$this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){throw new CitaException(['transaccion'=>'La consulta requiere una transacción compatible.'],409);}
            $this->pdo->exec('SAVEPOINT portal_lectura');$savepoint=true;$p=$this->identidad(true);$r=$accion((int)$p['id']);
            $this->pdo->exec('RELEASE SAVEPOINT portal_lectura');$savepoint=false;if($propia){$this->pdo->commit();}return $r;
        }catch(\Throwable $e){
            if($this->pdo->inTransaction()){if($propia){$this->pdo->rollBack();}elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT portal_lectura');$this->pdo->exec('RELEASE SAVEPOINT portal_lectura');}}
            if($e instanceof CitaException||$e instanceof CatalogoValidacionException){throw $e;}
            throw new CitaException(['servicio'=>'No se pudo completar la consulta. Inténtalo más tarde.'],503);
        }
    }
    private function presentar(array $c): array
    {
        $d=(new DateTimeImmutable($c['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        return $c+['codigo'=>Cita::codigo((int)$c['id']),'inicio_local'=>$d->format('d/m/Y H:i'),'fin_local'=>$d->modify('+'.(int)$c['duracion_minutos'].' minutes')->format('d/m/Y H:i')];
    }
    private function propia(int $paciente,int $id): array
    {
        $c=$this->historial->cita($paciente,$id,null);
        if(!$c){throw new CitaException(['cita'=>'Recurso no disponible.'],404);}return $this->presentar($c);
    }
    public function listar(array $e,bool $historico=false): array
    {
        self::parametros($e);
        return $this->leer(function(int $p)use($e,$historico):array{
            $f=FiltrosCita::validar(array_intersect_key($e,array_flip(['desde','hasta','estado','periodo','pagina']))+['periodo'=>$historico?'todas':'proximas']);
            foreach(['desde'=>'limite_desde','hasta'=>'limite_hasta'] as $k=>$v){if($f[$k]!==''){$d=new DateTimeImmutable($f[$k],HorarioAtencion::zona());$f[$v]=($k==='hasta'?$d->modify('+1 day'):$d)->format('c');}}
            $f['ahora']=$this->disponibilidad->ahora()->format('c');$r=$this->historial->listar($p,null,$f);$r['filas']=array_map($this->presentar(...),$r['filas']);
            return ['resultado'=>$r,'filtros'=>$f];
        });
    }
    public function detalle(int $id,array $e=[]): array
    {
        self::parametros($e);return $this->leer(function(int $p)use($id,$e):array{
            $c=$this->propia($p,$id);$f=FiltrosCita::validar(array_intersect_key($e,['pagina'=>true]));
            $r=$this->historial->eventos($p,$id,null,$f['pagina']===''?1:(int)$f['pagina'],false);
            foreach($r['filas'] as &$h){foreach(['inicio_anterior','inicio_nuevo','cambiado_en'] as $k){$h[$k]=(new DateTimeImmutable($h[$k]))->setTimezone(HorarioAtencion::zona())->format('d/m/Y H:i');}}unset($h);
            return ['cita'=>$c,'eventos'=>$r];
        });
    }
    public function formulario(array $e,?int $id=null,string $accion='nueva'): array
    {
        self::parametros($e);return $this->leer(function(int $p)use($e,$id,$accion):array{
            $c=$id===null?null:$this->propia($p,$id);$original=null;
            if($id!==null){$interno=$accion==='cancelar'?$this->cancelaciones->obtener($id):$this->reprogramaciones->obtener($id);$original=$interno['original'];}
            $repo=new DisponibilidadRepository($this->pdo);$sel=[];
            foreach(['especialidad_id','medico_id','fecha','hora_inicio'] as $k){$v=$e[$k]??'';if(!is_string($v)&&!is_int($v)){throw new CitaException(['seleccion'=>'Selecciona valores válidos.']);}$sel[$k]=(string)$v;}
            if($sel['fecha']===''){$sel['fecha']=$this->disponibilidad->ahora()->format('Y-m-d');}
            foreach(['especialidad_id','medico_id'] as $k){if($sel[$k]!==''&&(!filter_var($sel[$k],FILTER_VALIDATE_INT)||(int)$sel[$k]<=0)){throw new CitaException(['seleccion'=>'Selecciona identificadores válidos.']);}}
            if($id!==null){$sel['especialidad_id']=(string)$interno['especialidad_id'];$sel['medico_id']=(string)$interno['medico_id'];}
            $especialidades=$repo->especialidades();$medicos=$sel['especialidad_id']===''?[]:$repo->medicos((int)$sel['especialidad_id']);$turnos=[];
            if($accion!=='cancelar'&&$sel['medico_id']!==''&&$sel['especialidad_id']!==''){
                $d=$this->disponibilidad->consultar((int)$sel['medico_id'],$sel['fecha'],(int)$sel['especialidad_id'],$id);$turnos=$d['turnos'];
            }
            return ['cita'=>$c,'original'=>$original,'seleccion'=>$sel,'especialidades'=>$especialidades,'medicos'=>$medicos,'turnos'=>$turnos,'accionPortal'=>$accion];
        });
    }
    public function reservar(array $e): array
    {
        self::parametros($e);if(($e['confirmar']??null)!=='1'){throw new CitaException(['confirmacion'=>'Confirma expresamente la reserva.']);}
        $p=$this->identidad();$datos=array_intersect_key($e,array_flip(['especialidad_id','medico_id','fecha','hora_inicio']));$datos['paciente_id']=(int)$p['id'];
        return (new CitaService($this->pdo,$this->citas,$this->disponibilidad))->crear($datos,$this->actorId);
    }
    public function cambiar(int $id,string $accion,array $e): array
    {
        self::parametros($e);$p=$this->identidad();$this->propia((int)$p['id'],$id);
        if(($e['confirmar']??null)!=='1'){throw new CitaException(['confirmacion'=>'Confirma expresamente la operación.']);}
        if($accion==='cancelar'){return $this->cancelaciones->cancelar($id,array_intersect_key($e,array_flip(['original','motivo_cancelacion']))+['accion'=>'confirmar'],$this->actorId);}
        if($accion==='reprogramar'){return $this->reprogramaciones->reprogramar($id,array_intersect_key($e,array_flip(['original','fecha','hora_inicio'])),$this->actorId);}
        throw new CitaException(['accion'=>'Operación no disponible.'],404);
    }
}
