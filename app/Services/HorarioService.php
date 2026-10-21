<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\HorarioAtencion;
use App\Repositories\HorarioRepository;
use DateTimeImmutable;
use PDO;
use PDOException;

final class HorarioService
{
    public function __construct(private readonly PDO $pdo, private readonly HorarioRepository $repo) {}
    public function validar(array $entrada): array
    {
        $errores=[]; $datos=[];
        foreach (['medico_id','dia_semana'] as $campo) {
            $valor=$entrada[$campo]??'';
            $id=is_string($valor)||is_int($valor) ? filter_var($valor,FILTER_VALIDATE_INT) : false;
            if ($id === false || $id<=0 || ($campo==='dia_semana' && !isset(HorarioAtencion::DIAS[$id]))) { $errores[$campo]='Selecciona una opción válida.'; }
            $datos[$campo]=$id;
        }
        foreach (['hora_inicio','hora_fin'] as $campo) {
            $hora=$entrada[$campo]??null;
            if (!is_string($hora) || !preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30)$/D',$hora)) { $errores[$campo]='Usa una hora válida de 24 horas, con minutos 00 o 30.'; }
            $datos[$campo]=is_string($hora)?$hora:'';
        }
        if (!isset($errores['hora_inicio']) && !isset($errores['hora_fin']) && $datos['hora_fin']<=$datos['hora_inicio']) {
            $errores['hora_fin']='La hora final debe ser posterior a la inicial, dentro del mismo día.';
        }
        if ($errores) { throw new CatalogoValidacionException($errores); }
        return $datos;
    }
    public function obtener(int $id): array { return $this->repo->obtener($id) ?? throw new \OutOfBoundsException(); }

    private function transaccion(int $medico, callable $accion): mixed
    {
        $propia=!$this->pdo->inTransaction();
        if ($propia) { $this->pdo->beginTransaction(); }
        $this->pdo->exec('SAVEPOINT horario_medico');
        try {
            // Todos los cambios de CU11 toman esta misma fila antes de comprobar.
            // Coordina altas/ediciones/estados y el FOR SHARE del trigger de citas.
            $m=$this->repo->medico($medico,true);
            if (!$m) { throw new CatalogoValidacionException(['medico_id'=>'El médico no existe.']); }
            $resultado=$accion($m);
            $this->pdo->exec('RELEASE SAVEPOINT horario_medico');
            if ($propia) { $this->pdo->commit(); }
            return $resultado;
        } catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT horario_medico'); $this->pdo->exec('RELEASE SAVEPOINT horario_medico');
            if ($propia) { $this->pdo->rollBack(); }
            throw $e;
        }
    }
    private function validarActivo(array $medico, array $datos, ?int $id): void
    {
        if (!$medico['activo']) { throw new CatalogoValidacionException(['medico_id'=>'Un médico inactivo no puede recibir horarios activos.']); }
        if ($this->repo->solapa($datos['medico_id'],$datos['dia_semana'],$datos['hora_inicio'],$datos['hora_fin'],$id)) {
            throw new CatalogoValidacionException(['hora_inicio'=>'Este tramo se superpone con otro horario activo del mismo médico y día.']);
        }
    }
    private function protegerCitas(int $medico, array $dias): void
    {
        if ($this->repo->citasSinCobertura($medico,$dias,HorarioAtencion::zona()->getName())) {
            throw new CatalogoValidacionException(['citas'=>'La operación dejaría una cita futura programada fuera de los horarios válidos. Se conservaron el horario y la cita.']);
        }
    }
    public function guardar(array $entrada, ?int $id=null): int
    {
        $datos=$this->validar($entrada);
        if ($id !== null && (int)$this->obtener($id)['medico_id']!==$datos['medico_id']) {
            throw new CatalogoValidacionException(['medico_id'=>'El médico del horario no puede cambiarse. Crea un horario separado.']);
        }
        try {
            return $this->transaccion($datos['medico_id'],function(array $medico) use($datos,$id): int {
                $actual=$id === null ? null : ($this->repo->obtener($id,true) ?? throw new \OutOfBoundsException());
                if ($actual === null || $actual['activo']) { $this->validarActivo($medico,$datos,$id); }
                $guardado=$this->repo->guardar($datos,$id);
                if ($actual !== null && $actual['activo']) { $this->protegerCitas($datos['medico_id'],[(int)$actual['dia_semana'],$datos['dia_semana']]); }
                return $guardado;
            });
        } catch (PDOException $e) {
            if ($e->getCode()==='23505') { throw new CatalogoValidacionException(['hora_inicio'=>'Ya existe un horario con ese médico, día e inicio, incluso si está inactivo. Edita o reactiva ese registro.']); }
            throw $e;
        }
    }
    public function cambiarEstado(int $id, bool $activo): void
    {
        $inicial=$this->obtener($id);
        $this->transaccion((int)$inicial['medico_id'],function(array $medico) use($id,$activo): void {
            $actual=$this->repo->obtener($id,true) ?? throw new \OutOfBoundsException();
            if ($activo) {
                $datos=$actual;
                foreach (['hora_inicio','hora_fin'] as $campo) {
                    $datos[$campo]=preg_match('/^([0-9]{2}:[0-9]{2}):00(?:\.0+)?$/D',$actual[$campo],$hora) ? $hora[1] : $actual[$campo];
                }
                $this->validar($datos);
                $this->validarActivo($medico,$datos,$id);
            }
            $this->repo->cambiarEstado($id,$activo);
            if (!$activo && $actual['activo']) { $this->protegerCitas((int)$actual['medico_id'],[(int)$actual['dia_semana']]); }
        });
    }
    /** Tramos semanales activos; no calcula turnos libres ni reserva citas. */
    public function tramosParaFecha(int $medico, string $fecha): array
    {
        $dia=preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$fecha) ? DateTimeImmutable::createFromFormat('!Y-m-d',$fecha,HorarioAtencion::zona()) : false;
        if (!$dia || $dia->format('Y-m-d')!==$fecha || $fecha<'0001-01-01') { throw new CatalogoValidacionException(['fecha'=>'Ingresa una fecha válida.']); }
        $m=$this->repo->medico($medico);
        if (!$m) { throw new \OutOfBoundsException(); }
        return $m['activo'] ? $this->repo->activos($medico,(int)$dia->format('N')) : [];
    }
}
