<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\HorarioAtencion;
use App\Repositories\PerfilClinicoRepository;
use Closure;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/** Autorización clínica común CU23/CU24; misma política diaria y orden de bloqueos. */
final class AccesoClinicoService
{
    public function __construct(private readonly PDO $pdo,private readonly PerfilClinicoRepository $repo,
        private readonly int $actorId,private readonly ?Closure $reloj=null) {}
    private function ahora(): DateTimeImmutable
    {
        $d=$this->reloj===null?new DateTimeImmutable($this->pdo->query('SELECT clock_timestamp()')->fetchColumn()):($this->reloj)();
        return $d->setTimezone(HorarioAtencion::zona());
    }
    private function autorizar(int $paciente,bool $escritura): array
    {
        $actor=$this->repo->actor($this->actorId);
        if(!$actor||!$actor['activo']||$actor['rol']!=='medico'){throw new PerfilClinicoException(['permisos'=>'No tienes acceso a información clínica.'],403);}
        // El médico se bloquea antes que cita/paciente, como en CU11–CU18.
        $medico=$this->repo->medico($this->actorId,$escritura);
        if(!$medico){throw new PerfilClinicoException(['paciente'=>'Recurso no disponible.'],404);}
        $p=$this->repo->paciente($paciente,$escritura);
        if(!$p){throw new PerfilClinicoException(['paciente'=>'Recurso no disponible.'],404);}
        $dia=$this->ahora()->setTime(0,0);
        $cita=$this->repo->relacion($paciente,(int)$medico['id'],$dia->format('c'),$dia->modify('+1 day')->format('c'));
        $actor=$this->repo->actor($this->actorId,true);
        if(!$actor||!$actor['activo']||$actor['rol']!=='medico'){throw new PerfilClinicoException(['permisos'=>'No tienes acceso a información clínica.'],403);}
        if(!$cita||(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona())->format('Y-m-d')!==$this->ahora()->format('Y-m-d')){
            throw new PerfilClinicoException(['paciente'=>'Recurso no disponible.'],404);
        }
        return $p;
    }
    public function operar(int $paciente,bool $escritura,Closure $operacion): mixed
    {
        $propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            if($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'||$this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){
                throw new PerfilClinicoException(['transaccion'=>'La operación requiere una transacción compatible.'],409);
            }
            $this->pdo->exec('SAVEPOINT perfil_clinico');$savepoint=true;
            $p=$this->autorizar($paciente,$escritura);$resultado=$operacion($p);
            // No confirmar una operación cuya ventana asistencial venció durante su ejecución.
            $this->autorizar($paciente,$escritura);
            $this->pdo->exec('RELEASE SAVEPOINT perfil_clinico');$savepoint=false;
            if($propia){$this->pdo->commit();}return $resultado;
        }catch(Throwable $e){
            if($this->pdo->inTransaction()){
                if($propia){$this->pdo->rollBack();}
                elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT perfil_clinico');$this->pdo->exec('RELEASE SAVEPOINT perfil_clinico');}
            }
            if($e instanceof PerfilClinicoException){throw $e;}
            if($e instanceof PDOException&&in_array((string)$e->getCode(),['23505','40P01','40001','55P03'],true)){
                throw new PerfilClinicoException(['conflicto'=>'Ya existe un registro activo equivalente o hubo otro cambio. Recarga la página y revisa los datos.'],409);
            }
            throw new PerfilClinicoException(['servicio'=>'No se pudo completar la operación. No se guardaron cambios parciales.'],503);
        }
    }
}
