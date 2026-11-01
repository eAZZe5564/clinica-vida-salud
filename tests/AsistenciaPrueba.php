<?php
declare(strict_types=1);
require_once __DIR__.'/ConfirmacionPrueba.php';

/** Solo desarrollo local; cada fixture conserva sus IDs y se elimina por ellos. */
final class AsistenciaPrueba
{
    public readonly ConfirmacionPrueba $previa;
    public readonly CitasPrueba $t;
    public function __construct(public readonly PDO $pdo)
    {
        if(!$pdo->query("SELECT to_regclass('public.citas_historial_asistencia_unica_idx')")->fetchColumn()){
            throw new RuntimeException('CU18 requiere migración 008 previamente autorizada y aplicada.');
        }
        $this->previa=new ConfirmacionPrueba($pdo);$this->t=$this->previa->c->r->t;
    }
    public function servicio(?DateTimeImmutable $ahora=null,?PDO $conexion=null): App\Services\AsistenciaService
    {
        $pdo=$conexion??$this->pdo;
        return new App\Services\AsistenciaService($pdo,new App\Repositories\CitaRepository($pdo),$ahora===null?null:static fn():DateTimeImmutable=>$ahora);
    }
    public function datos(int $id): array
    {
        $fila=$this->fila($id);$inicio=(new DateTimeImmutable($fila['fecha_hora_inicio']))->setTimezone(App\Models\HorarioAtencion::zona());
        $revision=(new App\Repositories\CitaRepository($this->pdo))->revisionReprogramacion($id);
        return ['accion'=>'confirmar','original'=>$inicio->format('Y-m-d\TH:i:s.uP').'|'.$revision.'|'.$fila['estado']];
    }
    /** Hora cercana real para comprobar llegada/finalización sin cambiar relojes o triggers. */
    public function citaReal(int $medico,int $paciente,int $especialidad,int $actor): int
    {
        $q=$this->pdo->prepare("INSERT INTO citas(medico_id,paciente_id,especialidad_id,fecha_hora_inicio,creada_por_usuario_id)
            VALUES (:m,:p,:e,clock_timestamp()+interval '2 seconds',:a) RETURNING id");
        $q->execute(['m'=>$medico,'p'=>$paciente,'e'=>$especialidad,'a'=>$actor]);return (int)$q->fetchColumn();
    }
    public function esperarInicio(int $id): void
    {
        $q=$this->pdo->prepare('SELECT clock_timestamp()>=fecha_hora_inicio FROM citas WHERE id=:id');
        for($i=0;$i<50;++$i){$q->execute(['id'=>$id]);if($q->fetchColumn()){return;}usleep(100000);}
        throw new RuntimeException('No llegó el inicio del fixture de reloj real.');
    }
    public function fila(int $id): array{return $this->previa->c->r->fila($id);}
    public function eventos(int $id): array{return $this->previa->c->eventos($id);}
    public function limpiar(): void{$this->previa->limpiar();}
}
