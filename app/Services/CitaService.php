<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\CitaRepository;
use DateTimeImmutable;
use PDO;
use PDOException;

/** CU13: reserva atómica con revalidación de CU12 bajo el bloqueo de CU11. */
final class CitaService
{
    public function __construct(private readonly PDO $pdo,private readonly CitaRepository $repo,private readonly DisponibilidadService $disponibilidad) {}
    public function validar(array $entrada): array
    {
        $errores=[];$datos=[];
        foreach(['paciente_id','medico_id','especialidad_id'] as $campo){
            $valor=$entrada[$campo]??null;$id=is_int($valor)||is_string($valor)?filter_var($valor,FILTER_VALIDATE_INT):false;
            if($id===false||$id<=0){$errores[$campo]='Selecciona una opción válida.';}$datos[$campo]=$id;
        }
        $fecha=$entrada['fecha']??null;$hora=$entrada['hora_inicio']??null;
        if(!is_string($hora)||!preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30)$/D',$hora)){$errores['hora_inicio']='Usa una hora válida en intervalos de 30 minutos.';}
        try{$this->disponibilidad->validarParametros(['medico_id'=>$datos['medico_id'],'especialidad_id'=>$datos['especialidad_id'],'fecha'=>$fecha]);}
        catch(CatalogoValidacionException $e){$errores=array_merge($errores,$e->errores);}
        $motivo=CatalogoValidacion::texto($entrada['motivo']??'','motivo',500,false,$errores);
        if($errores){throw new CitaException($errores);}
        $inicio=new DateTimeImmutable($fecha . ' ' . $hora . ':00',HorarioAtencion::zona());
        if($inicio<=$this->disponibilidad->ahora()){throw new CitaException(['hora_inicio'=>'La cita debe comenzar en el futuro.']);}
        return $datos+['fecha'=>$fecha,'hora_inicio'=>$hora,'inicio'=>$inicio,'motivo'=>$motivo];
    }
    public function crear(array $entrada,int $actorId): array
    {
        $datos=$this->validar($entrada);$propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            elseif($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed' || $this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){
                throw new CitaException(['transaccion'=>'La reserva requiere una transacción de escritura READ COMMITTED.'],409);
            }
            $this->pdo->exec('SAVEPOINT programar_cita');$savepoint=true;
            // Orden único: médico → especialidad → asociación → paciente → actor.
            // SHARE permite reservas simultáneas; CU11 usa UPDATE para cambiar horarios.
            $medico=$this->repo->bloquearMedico($datos['medico_id']);
            if(!$medico||!$medico['activo']){throw new CitaException(['medico_id'=>'El médico no existe o está inactivo.']);}
            $especialidad=$this->repo->bloquearEspecialidad($datos['especialidad_id']);
            if(!$especialidad||!$especialidad['activo']||!$this->repo->bloquearAsociacion($datos['medico_id'],$datos['especialidad_id'])){
                throw new CitaException(['especialidad_id'=>'Selecciona una especialidad activa asociada al médico.']);
            }
            $paciente=$this->repo->bloquearPaciente($datos['paciente_id']);
            if(!$paciente||!$paciente['activo']){throw new CitaException(['paciente_id'=>'El paciente no existe o está inactivo.']);}
            $actor=$this->repo->bloquearActor($actorId);
            if(!$actor||!$actor['activo']||!in_array($actor['rol'],['administrador','recepcionista'],true)){
                throw new CitaException(['permisos'=>'No tienes autorización para programar citas.'],403);
            }
            // No usar una consulta previa: CU12 se ejecuta aquí, con la misma conexión
            // y transacción, después del bloqueo que impide cambios de horario de CU11.
            $libres=$this->disponibilidad->consultar($datos['medico_id'],$datos['fecha'],$datos['especialidad_id']);$valido=false;
            foreach($libres['turnos'] as $turno){if((new DateTimeImmutable($turno['inicio']))->getTimestamp()===$datos['inicio']->getTimestamp()){$valido=true;break;}}
            if(!$valido){throw new CitaException(['disponibilidad'=>'El turno ya no está disponible o no pertenece al horario activo. Consulta nuevamente la disponibilidad.'],409);}
            $fila=$this->repo->crear(['paciente_id'=>$datos['paciente_id'],'medico_id'=>$datos['medico_id'],'especialidad_id'=>$datos['especialidad_id'],
                'inicio'=>$datos['inicio']->format('c'),'duracion'=>HorarioAtencion::DURACION_MINUTOS,'motivo'=>$datos['motivo'],'actor'=>$actorId]);
            // Dos lectores SHARE pueden haber visto libre el mismo turno. GiST decide:
            // solo una inserción confirma; la otra espera y recibe 23P01, que revertimos.
            $inicio=(new DateTimeImmutable($fila['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
            $resultado=['id'=>(int)$fila['id'],'codigo'=>Cita::codigo((int)$fila['id']),'inicio'=>$inicio->format('c'),
                'fin'=>$inicio->modify('+' . (int)$fila['duracion_minutos'] . ' minutes')->format('c'),'estado'=>$fila['estado'],'creada_en'=>$fila['creada_en']];
            $this->pdo->exec('RELEASE SAVEPOINT programar_cita');$savepoint=false;
            if($propia){$this->pdo->commit();}return $resultado;
        }catch(\Throwable $e){
            if($this->pdo->inTransaction()){
                if($propia){$this->pdo->rollBack();}
                elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT programar_cita');$this->pdo->exec('RELEASE SAVEPOINT programar_cita');}
            }
            if($e instanceof PDOException){throw CitaException::integridad($e);}
            if($e instanceof CatalogoValidacionException){throw new CitaException($e->errores);}
            throw $e;
        }
    }
}
