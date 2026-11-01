<?php
declare(strict_types=1);
require_once __DIR__.'/AgendaPrueba.php';

/** Solo fixtures ficticios rastreados por los auxiliares ya existentes. */
final class HistorialPacientePrueba
{
    public readonly AgendaPrueba $base;
    public int $vacio;
    public int $ajeno;
    public int $unico;
    public int $unicaCita;
    public int $legado;
    public array $antiguas=[];

    public function __construct(public readonly PDO $pdo)
    {
        $this->base=new AgendaPrueba($pdo);$f=$this->base->t->fixture;
        $this->vacio=$f->paciente();$this->ajeno=$f->paciente();$this->unico=$f->paciente();
        $this->pdo->prepare('UPDATE pacientes SET usuario_id=:u WHERE id=:p')->execute(['u'=>$this->base->actores['paciente']['id'],'p'=>$this->base->paciente]);
        $a=$this->base->actores['administrador']['id'];$r=$this->base->asistencia->previa->c->r;
        $r->servicio->reprogramar($this->base->ids['programada'],$r->datos($this->base->ids['programada'],'11:00',$this->base->fecha),$a);
        $this->base->t->servicio->crear($this->base->t->datos($this->base->otro,$this->ajeno,$f->especialidades[1],'08:30'),$a);
        $this->unicaCita=$this->base->t->servicio->crear($this->base->t->datos($this->base->medico,$this->unico,$this->base->especialidad,'11:30'),$a)['id'];
        for($i=0;$i<32;++$i){$fecha=(new DateTimeImmutable('2015-01-01 08:00',App\Models\HorarioAtencion::zona()))->modify('+'.intdiv($i,2).' days');$this->antiguas[]=$this->base->insertar($fecha->format('c'),'cancelada');}
        $q=$pdo->prepare("INSERT INTO citas(medico_id,paciente_id,fecha_hora_inicio,estado) VALUES (:m,:p,'2010-01-01T08:00:00-04:00','atendida') RETURNING id");
        $q->execute(['m'=>$this->base->medico,'p'=>$this->base->paciente]);$this->legado=(int)$q->fetchColumn();
    }
    public function servicio(string $rol='administrador',?PDO $conexion=null): App\Services\HistorialPacienteService
    {
        $p=$conexion??$this->pdo;
        $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($p),new App\Repositories\HorarioRepository($p),$this->base->actores[$rol]);
        return new App\Services\HistorialPacienteService(new App\Repositories\HistorialPacienteRepository($p),$consulta);
    }
    public function consultar(array $f=[],string $rol='administrador',?int $paciente=null): array
    {return $this->servicio($rol)->consultar($paciente??$this->base->paciente,$f);}
    public function reprogramaciones(int $veces=22): void
    {
        $r=$this->base->asistencia->previa->c->r;$a=$this->base->actores['administrador']['id'];$id=$this->base->ids['programada'];
        for($i=0;$i<$veces;++$i){$r->servicio->reprogramar($id,$r->datos($id,$i%2===0?'08:00':'11:00',$this->base->fecha),$a);}
    }
    public function limpiar(): void{$this->base->limpiar();}
}
