<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\CitaRepository;
use DateTimeImmutable;
use PDO;
use PDOException;

/** CU17: confirma la misma reserva; estado e historial se guardan juntos. */
final class ConfirmacionService
{
    public function __construct(private readonly PDO $pdo,private readonly CitaRepository $repo) {}

    public function obtener(int $id): array
    {
        $cita=$this->repo->paraReprogramar($id);
        if($cita===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
        if($cita['estado']!=='programada'){
            throw new CitaException(['estado'=>'Solo se pueden confirmar citas programadas. No se puede confirmar nuevamente una cita ni confirmar una cancelada o atendida.'],409);
        }
        $cita['codigo']=Cita::codigo((int)$cita['id']);
        $inicio=(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        $cita['original']=$inicio->format('Y-m-d\TH:i:s.uP').'|'.$this->repo->revisionReprogramacion($id);
        $cita['horario_actual']=$inicio->format('d/m/Y H:i').'–'.$inicio->modify('+'.$cita['duracion_minutos'].' minutes')->format('H:i');
        return $cita;
    }

    public function confirmar(int $id,array $entrada,int $actorId): array
    {
        if(($entrada['accion']??null)!=='confirmar'){
            throw new CitaException(['confirmacion'=>'Confirma explícitamente la cita.']);
        }
        $propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            elseif($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'||$this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){
                throw new CitaException(['transaccion'=>'La confirmación requiere READ COMMITTED de escritura.'],409);
            }
            $this->pdo->exec('SAVEPOINT confirmar_cita');$savepoint=true;
            $inicial=$this->obtener($id);
            // Orden CU11/CU13/CU15/CU16; confirmar no cambia horario o catálogos.
            if(!$this->repo->bloquearMedico((int)$inicial['medico_id'])){throw new CitaException(['medico'=>'El médico no existe.'],409);}
            $bloqueada=$this->repo->paraReprogramar($id,true);
            if($bloqueada===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
            if((int)$bloqueada['medico_id']!==(int)$inicial['medico_id']){throw new CitaException(['cita'=>'La cita cambió. Vuelve a consultarla.'],409);}
            $cita=$this->obtener($id);
            $original=$entrada['original']??null;
            if(!is_string($original)||strlen($original)>80||$original!==$cita['original']){
                throw new CitaException(['cita'=>'La cita cambió desde que abriste la confirmación. Revisa el horario actual antes de confirmar.'],409);
            }
            $actor=$this->repo->bloquearActor($actorId);
            if(!$actor||!$actor['activo']||!in_array($actor['rol'],['administrador','recepcionista'],true)){
                throw new CitaException(['permisos'=>'No tienes autorización para confirmar citas.'],403);
            }
            $this->repo->confirmarProgramada($id);
            $this->repo->registrarConfirmacion($cita,$actorId);
            $this->pdo->exec('RELEASE SAVEPOINT confirmar_cita');$savepoint=false;
            if($propia){$this->pdo->commit();}
            return ['id'=>$id,'codigo'=>$cita['codigo'],'estado'=>'confirmada'];
        }catch(\Throwable $e){
            if($this->pdo->inTransaction()){
                if($propia){$this->pdo->rollBack();}
                elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT confirmar_cita');$this->pdo->exec('RELEASE SAVEPOINT confirmar_cita');}
            }
            if($e instanceof PDOException){
                $conflicto=in_array((string)$e->getCode(),['40P01','40001','55P03','23P01'],true);
                throw new CitaException(['confirmacion'=>$conflicto?'La operación coincidió con otro cambio. Consulta de nuevo la cita.':'No se pudo confirmar la cita. Su estado anterior se conserva. Inténtalo más tarde.'],$conflicto?409:503,(string)$e->getCode());
            }
            throw $e;
        }
    }
}
