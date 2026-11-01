<?php
declare(strict_types=1);
require_once __DIR__.'/Cu15Local.php';
require_once __DIR__.'/AsistenciaPrueba.php';

/** Fixtures ficticios con historial auténtico CU15–CU18 y limpieza por sus propios IDs. */
final class AgendaPrueba
{
    public readonly AsistenciaPrueba $asistencia;
    public readonly CitasPrueba $t;
    public array $actores=[];
    public array $ids=[];
    public int $medico;
    public int $otro;
    public int $paciente;
    public int $especialidad;
    public string $fecha;

    public function __construct(public readonly PDO $pdo)
    {
        $this->asistencia=new AsistenciaPrueba($pdo);$this->t=$this->asistencia->t;$f=$this->t->fixture;
        foreach(['administrador','recepcionista','medico','paciente','sin_vinculo','otro_medico'] as $rol){
            $id=$f->usuario(in_array($rol,['sin_vinculo','otro_medico'],true)?'medico':$rol);
            $q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
            $this->actores[$rol]=['id'=>$id,'rol'=>in_array($rol,['sin_vinculo','otro_medico'],true)?'medico':$rol,'correo'=>$q->fetchColumn()];
        }
        $this->medico=$f->medico($this->actores['medico']['id']);$this->especialidad=$f->especialidades[0];
        $this->otro=$f->medico($this->actores['otro_medico']['id']);$this->paciente=$f->paciente();
        $pdo->prepare("UPDATE pacientes SET nombres=:n,documento_identidad=:ci,telefono='70001122',direccion='DIRECCION_PRIVADA_CU19' WHERE id=:id")
            ->execute(['n'=>'Paciente <b>ficticio CU19</b>','ci'=>'CI-PRIVADO-'.$f->sufijo,'id'=>$this->paciente]);
        $this->t->horario($this->medico);$this->t->horario($this->otro);$this->fecha=$this->t->lunes->format('Y-m-d');
        $this->t->horarios->guardar(['medico_id'=>$this->medico,'dia_semana'=>1,'hora_inicio'=>'14:00','hora_fin'=>'15:00']);
        $this->t->horarios->guardar(['medico_id'=>$this->medico,'dia_semana'=>2,'hora_inicio'=>'08:00','hora_fin'=>'10:00']);
        $a=$this->actores['administrador']['id'];$d=$this->actores['medico']['id'];
        foreach(['programada'=>'08:00','confirmada'=>'08:30','presente'=>'09:00','atendida'=>'09:30','inasistencia'=>'10:00','cancelada'=>'10:30'] as $estado=>$hora){
            $id=$this->t->servicio->crear($this->t->datos($this->medico,$this->paciente,$this->especialidad,$hora)+['motivo'=>'MOTIVO_CLINICO_PRIVADO_CU19'],$a)['id'];
            $this->ids[$estado]=$id;
            if($estado==='confirmada'){$this->asistencia->previa->servicio->confirmar($id,$this->asistencia->previa->datos($id),$a);}
            elseif($estado==='cancelada'){$this->asistencia->previa->c->servicio->cancelar($id,$this->asistencia->previa->c->datos($id),$a);}
            elseif(in_array($estado,['presente','atendida','inasistencia'],true)){
                $s=$this->asistencia->servicio($this->t->lunes->setTime(11,0));
                $s->registrar($id,$estado==='inasistencia'?'inasistencia':'llegada',$this->asistencia->datos($id),$a);
                if($estado==='atendida'){$s->registrar($id,'atencion',$this->asistencia->datos($id),$d);}
            }
        }
        $this->ids['reemplazo']=$this->t->servicio->crear($this->t->datos($this->medico,$this->paciente,$this->especialidad,'10:30'),$a)['id'];
        $this->ids['ajena']=$this->t->servicio->crear($this->t->datos($this->otro,$this->paciente,$f->especialidades[1]),$a)['id'];
    }

    public function agenda(string $rol='administrador',?PDO $pdo=null): App\Services\AgendaService
    {
        $p=$pdo??$this->pdo;
        return new App\Services\AgendaService(new App\Repositories\AgendaRepository($p),new App\Repositories\HorarioRepository($p),$this->actores[$rol]);
    }
    public function consultar(string $vista='diaria',array $filtros=[],string $rol='administrador'): array
    {
        return $this->agenda($rol)->consultar($vista,array_replace(['fecha'=>$this->fecha,'medico_id'=>$this->medico],$filtros));
    }
    public static function citas(array $agenda): array
    {
        $citas=[];foreach($agenda['dias'] as $dia){foreach($dia['eventos'] as $evento){if($evento['tipo']==='cita'){$citas[]=$evento['cita'];}}}return $citas;
    }
    public function insertar(string $fecha,string $estado='atendida'): int
    {
        // Estados/fechas de legado para bordes de calendario; no se fabrica historial CU18.
        $q=$this->pdo->prepare('INSERT INTO citas(medico_id,paciente_id,especialidad_id,fecha_hora_inicio,estado) VALUES (:m,:p,:e,:f,:s) RETURNING id');
        $q->execute(['m'=>$this->medico,'p'=>$this->paciente,'e'=>$this->especialidad,'f'=>$fecha,'s'=>$estado]);return (int)$q->fetchColumn();
    }
    public function huella(): array
    {
        $result=[];
        foreach(['roles','usuarios','pacientes','medicos','especialidades','medicos_especialidades','horarios_atencion','citas','citas_historial'] as $tabla){
            $result[$tabla]=$this->pdo->query('SELECT count(*) AS filas,md5(COALESCE(string_agg(md5(row_to_json(t)::text),\'\' ORDER BY md5(row_to_json(t)::text)),\'\')) AS huella FROM '.$tabla.' t')->fetch();
        }return $result;
    }
    public function limpiar(): void{$this->asistencia->limpiar();}
}

/** PDO real vigilado: todas las sentencias de agenda deben ser SELECT preparados. */
final class AgendaLecturaPDO extends PDO
{
    public array $consultas=[];
    public function __construct(private readonly PDO $real) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if(!str_starts_with($query,'SELECT ')){throw new RuntimeException('La agenda intentó una sentencia distinta de SELECT.');}
        $this->consultas[]=$query;return $this->real->prepare($query,$options);
    }
}
