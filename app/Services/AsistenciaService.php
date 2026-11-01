<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\CitaRepository;
use App\Repositories\UsuarioRepository;
use Closure;
use DateTimeImmutable;
use PDO;
use PDOException;

/** CU18: presencia, finalización e inasistencia; sin información clínica. */
final class AsistenciaService
{
    public const ACCIONES=['llegada'=>'Registrar llegada','atencion'=>'Finalizar atención','inasistencia'=>'Registrar inasistencia'];
    private const DESTINOS=['llegada'=>'presente','atencion'=>'atendida','inasistencia'=>'inasistencia'];

    public function __construct(private readonly PDO $pdo,private readonly CitaRepository $repo,private readonly ?Closure $reloj=null) {}
    public function ahora(): DateTimeImmutable
    {
        $momento=$this->reloj===null?new DateTimeImmutable($this->pdo->query('SELECT clock_timestamp()')->fetchColumn()):($this->reloj)();
        return $momento->setTimezone(HorarioAtencion::zona());
    }
    /** Solo presentación; el POST revalida rol, vínculo, estado y tiempo bajo bloqueo. */
    public static function acciones(array $cita,string $rol,DateTimeImmutable $ahora): array
    {
        $acciones=[];$inicio=new DateTimeImmutable($cita['fecha_hora_inicio']);
        if(in_array($rol,['administrador','recepcionista'],true)&&in_array($cita['estado'],['programada','confirmada'],true)){
            $acciones['llegada']=self::ACCIONES['llegada'];
            if($ahora>=$inicio->modify('+'.$cita['duracion_minutos'].' minutes')){$acciones['inasistencia']=self::ACCIONES['inasistencia'];}
        }
        if($rol==='medico'&&$cita['estado']==='presente'&&$ahora>=$inicio){$acciones['atencion']=self::ACCIONES['atencion'];}
        return $acciones;
    }
    private function actor(int $id): array
    {
        $actor=(new UsuarioRepository($this->pdo))->buscarActivoPorId($id);
        if($actor===null){throw new CitaException(['permisos'=>'No tienes autorización para esta operación.'],403);}
        return ['id'=>(int)$actor['id'],'rol'=>$actor['rol_nombre']];
    }
    private function autorizarRol(string $operacion,array $actor): void
    {
        if(!isset(self::ACCIONES[$operacion])){throw new CitaException(['operacion'=>'La operación no existe.'],404);}
        $roles=$operacion==='atencion'?['medico']:['administrador','recepcionista'];
        if(!in_array($actor['rol'],$roles,true)){throw new CitaException(['permisos'=>'No tienes autorización para esta operación.'],403);}
    }
    private function autorizar(array $cita,string $operacion,array $actor): void
    {
        $this->autorizarRol($operacion,$actor);
        if($actor['rol']==='medico'&&(int)($cita['medico_usuario_id']??0)!==(int)$actor['id']){
            throw new CitaException(['cita'=>'La cita no existe.'],404);
        }
    }
    private function validar(array $cita,string $operacion,DateTimeImmutable $ahora): void
    {
        $estados=$operacion==='atencion'?['presente']:['programada','confirmada'];
        if(!in_array($cita['estado'],$estados,true)){
            throw new CitaException(['estado'=>$operacion==='atencion'?'Para finalizar la atención debe existir una llegada registrada y la cita no debe estar finalizada.':'La operación solo admite citas programadas o confirmadas, sin llegada ni cierre previos.'],409);
        }
        $inicio=(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        if($operacion==='atencion'&&$ahora<$inicio){throw new CitaException(['horario'=>'No se puede finalizar la atención antes del inicio programado.'],409);}
        if($operacion==='inasistencia'&&$ahora<$inicio->modify('+'.$cita['duracion_minutos'].' minutes')){
            throw new CitaException(['horario'=>'La inasistencia solo puede registrarse después de terminar el intervalo de la cita.'],409);
        }
    }
    private function presentar(array $cita): array
    {
        $inicio=(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        $cita['codigo']=Cita::codigo((int)$cita['id']);
        $cita['original']=$inicio->format('Y-m-d\TH:i:s.uP').'|'.$this->repo->revisionReprogramacion((int)$cita['id']).'|'.$cita['estado'];
        $cita['horario_actual']=$inicio->format('d/m/Y H:i').'–'.$inicio->modify('+'.$cita['duracion_minutos'].' minutes')->format('H:i');
        return $cita;
    }
    public function obtener(int $id,string $operacion,int $actorId): array
    {
        $actor=$this->actor($actorId);
        $this->autorizarRol($operacion,$actor);
        $cita=$this->repo->paraReprogramar($id);
        if($cita===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
        $this->autorizar($cita,$operacion,$actor);$this->validar($cita,$operacion,$this->ahora());
        return $this->presentar($cita);
    }
    public function registrar(int $id,string $operacion,array $entrada,int $actorId): array
    {
        if(($entrada['accion']??null)!=='confirmar'){throw new CitaException(['confirmacion'=>'Confirma explícitamente la operación.']);}
        $propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            elseif($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'||$this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){
                throw new CitaException(['transaccion'=>'La asistencia requiere READ COMMITTED de escritura.'],409);
            }
            $this->pdo->exec('SAVEPOINT asistencia_cita');$savepoint=true;
            $inicial=$this->obtener($id,$operacion,$actorId);
            if(!$this->repo->bloquearMedico((int)$inicial['medico_id'])){throw new CitaException(['medico'=>'El médico no existe.'],409);}
            $cita=$this->repo->paraReprogramar($id,true);
            if($cita===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
            if((int)$cita['medico_id']!==(int)$inicial['medico_id']){throw new CitaException(['cita'=>'La cita cambió. Vuelve a consultarla.'],409);}
            $actor=$this->repo->bloquearActor($actorId);
            if(!$actor||!$actor['activo']){throw new CitaException(['permisos'=>'No tienes autorización para esta operación.'],403);}
            $this->autorizar($cita,$operacion,$actor);
            $ahora=$this->ahora();$this->validar($cita,$operacion,$ahora);$cita=$this->presentar($cita);
            $original=$entrada['original']??null;
            if(!is_string($original)||strlen($original)>100||$original!==$cita['original']){
                throw new CitaException(['cita'=>'La cita cambió desde que abriste la pantalla. Revisa su estado y horario antes de confirmar.'],409);
            }
            $destino=self::DESTINOS[$operacion];
            $this->repo->actualizarAsistencia($id,$cita['estado'],$destino);
            $this->repo->registrarAsistencia($cita,$operacion,$destino,$actorId,$ahora->format('Y-m-d\TH:i:s.uP'));
            $this->pdo->exec('RELEASE SAVEPOINT asistencia_cita');$savepoint=false;
            if($propia){$this->pdo->commit();}
            return ['id'=>$id,'codigo'=>$cita['codigo'],'estado'=>$destino,'operacion'=>$operacion];
        }catch(\Throwable $error){
            if($this->pdo->inTransaction()){
                if($propia){$this->pdo->rollBack();}
                elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT asistencia_cita');$this->pdo->exec('RELEASE SAVEPOINT asistencia_cita');}
            }
            if($error instanceof PDOException){
                $conflicto=in_array((string)$error->getCode(),['40P01','40001','55P03','23P01','23505'],true);
                throw new CitaException(['asistencia'=>$conflicto?'La operación coincidió con otro registro. Consulta de nuevo la cita.':'No se pudo registrar la operación. El estado anterior y el historial se conservan.'],$conflicto?409:503,(string)$error->getCode());
            }
            throw $error;
        }
    }
}
