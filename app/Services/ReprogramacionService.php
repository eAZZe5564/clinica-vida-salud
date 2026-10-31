<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\CitaRepository;
use DateTimeImmutable;
use PDO;
use PDOException;

/** CU15: misma cita, validación CU12 y auditoría en una sola transacción. */
final class ReprogramacionService
{
    public function __construct(private readonly PDO $pdo,private readonly CitaRepository $repo,private readonly DisponibilidadService $disponibilidad) {}

    public function obtener(int $id): array
    {
        $cita=$this->repo->paraReprogramar($id);
        if($cita===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
        if($cita['estado']!=='programada'){
            throw new CitaException(['estado'=>'Solo se pueden reprogramar citas programadas. Las canceladas o atendidas conservan su horario.'],409);
        }
        if($cita['especialidad_id']===null){throw new CitaException(['especialidad'=>'La cita no tiene especialidad registrada. No se puede inferir ni reasignar en CU15.'],409);}
        $cita['codigo']=Cita::codigo((int)$cita['id']);
        $inicio=(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
        // El ID del último evento impide aceptar un formulario viejo incluso tras volver al mismo horario.
        $cita['original']=$inicio->format('Y-m-d\TH:i:s.uP') . '|' . $this->repo->revisionReprogramacion($id);
        $cita['horario_actual']=$inicio->format('d/m/Y H:i') . '–' . $inicio->modify('+30 minutes')->format('H:i');
        return $cita;
    }

    public function turnos(array $cita,string $fecha): array
    {
        return $this->disponibilidad->consultar((int)$cita['medico_id'],$fecha,(int)$cita['especialidad_id'],(int)$cita['id']);
    }

    private function validar(array $cita,array $entrada): DateTimeImmutable
    {
        $fecha=$entrada['fecha']??null;$hora=$entrada['hora_inicio']??null;
        $original=$entrada['original']??null;
        if(!is_string($original)||strlen($original)>80||$original!==$cita['original']){
            throw new CitaException(['cita'=>'La cita cambió desde que abriste el formulario. Revisa su horario actual y vuelve a confirmar.'],409);
        }
        try{$this->disponibilidad->validarParametros(['medico_id'=>$cita['medico_id'],'especialidad_id'=>$cita['especialidad_id'],'fecha'=>$fecha]);}
        catch(CatalogoValidacionException $e){throw new CitaException($e->errores);}
        if(!is_string($hora)||preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30)$/D',$hora)!==1){throw new CitaException(['hora_inicio'=>'Selecciona un turno de 30 minutos válido.']);}
        $inicio=new DateTimeImmutable($fecha . ' ' . $hora . ':00',HorarioAtencion::zona());
        if($inicio==new DateTimeImmutable($cita['fecha_hora_inicio'])){throw new CitaException(['sin_cambios'=>'Seleccionaste el horario actual. No existe ningún cambio que guardar.'],409);}
        if($inicio<=$this->disponibilidad->ahora()){throw new CitaException(['hora_inicio'=>'El nuevo horario debe comenzar en el futuro.']);}
        return $inicio;
    }

    public function previsualizar(int $id,array $entrada): array
    {
        $cita=$this->obtener($id);$inicio=$this->validar($cita,$entrada);
        $this->exigirTurno($cita,$inicio);
        return ['cita'=>$cita,'inicio'=>$inicio];
    }

    private function exigirTurno(array $cita,DateTimeImmutable $inicio): void
    {
        try{$turnos=$this->turnos($cita,$inicio->format('Y-m-d'));}
        catch(CatalogoValidacionException $e){throw new CitaException($e->errores);}
        foreach($turnos['turnos'] as $turno){if($inicio==new DateTimeImmutable($turno['inicio'])){return;}}
        throw new CitaException(['disponibilidad'=>'El turno ya no está disponible o está fuera de un horario de atención activo. El horario original se conserva.'],409);
    }

    public function reprogramar(int $id,array $entrada,int $actorId): array
    {
        $propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            elseif($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'||$this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){
                throw new CitaException(['transaccion'=>'La reprogramación requiere READ COMMITTED de escritura.'],409);
            }
            $this->pdo->exec('SAVEPOINT reprogramar_cita');$savepoint=true;
            $inicial=$this->obtener($id);
            // CU11 bloquea médico antes que horarios. Nunca bloquear cita antes que médico.
            $medico=$this->repo->bloquearMedico((int)$inicial['medico_id']);
            if(!$medico||!$medico['activo']){throw new CitaException(['medico'=>'El médico ya no está activo.']);}
            $bloqueada=$this->repo->paraReprogramar($id,true);
            if($bloqueada===null){throw new CitaException(['cita'=>'La cita no existe.'],404);}
            if((int)$bloqueada['medico_id']!==(int)$inicial['medico_id']){throw new CitaException(['cita'=>'La cita cambió. Vuelve a consultarla.'],409);}
            // Releer tras esperar FOR UPDATE: no validar la instantánea inicial.
            $cita=$this->obtener($id);$inicio=$this->validar($cita,$entrada);
            $especialidad=$this->repo->bloquearEspecialidad((int)$cita['especialidad_id']);
            if(!$especialidad||!$especialidad['activo']||!$this->repo->bloquearAsociacion((int)$cita['medico_id'],(int)$cita['especialidad_id'])){
                throw new CitaException(['especialidad'=>'La especialidad debe estar activa y asociada al mismo médico.']);
            }
            // El trigger existente también exige paciente activo: conservar esa garantía.
            $paciente=$this->repo->bloquearPaciente((int)$cita['paciente_id']);
            if(!$paciente||!$paciente['activo']){throw new CitaException(['paciente'=>'El paciente no está activo.']);}
            $actor=$this->repo->bloquearActor($actorId);
            if(!$actor||!$actor['activo']||!in_array($actor['rol'],['administrador','recepcionista'],true)){
                throw new CitaException(['permisos'=>'No tienes autorización para reprogramar citas.'],403);
            }
            $this->exigirTurno($cita,$inicio);
            $this->repo->actualizarHorario($id,$inicio->format('c'));
            $this->repo->registrarReprogramacion($cita,$inicio->format('c'),$actorId);
            $this->pdo->exec('RELEASE SAVEPOINT reprogramar_cita');$savepoint=false;
            if($propia){$this->pdo->commit();}
            return ['id'=>$id,'codigo'=>Cita::codigo($id),'inicio'=>$inicio->format('c'),'estado'=>'programada'];
        }catch(\Throwable $e){
            if($this->pdo->inTransaction()){
                if($propia){$this->pdo->rollBack();}
                elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT reprogramar_cita');$this->pdo->exec('RELEASE SAVEPOINT reprogramar_cita');}
            }
            if($e instanceof PDOException){throw CitaException::integridad($e);}
            throw $e;
        }
    }
}
