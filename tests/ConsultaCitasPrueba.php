<?php
declare(strict_types=1);
require_once __DIR__ . '/CitasPrueba.php';
/** Solo fixtures propios; conserva todos los registros preexistentes. */
final class ConsultaCitasPrueba
{
    public readonly CitasPrueba $t;
    public array $actores=[];
    public int $medico;
    public int $otro;
    public int $paciente;
    public int $especialidad;
    public int $reserva;
    public int $ajena;
    public int $historica;
    public int $legado;
    public array $canceladas=[];
    public function __construct(public readonly PDO $pdo)
    {
        if($pdo->query('SELECT current_database()')->fetchColumn()!=='clinica_vida_salud_dev'){throw new RuntimeException('Base no autorizada');}
        $this->t=new CitasPrueba($pdo);$f=$this->t->fixture;
        foreach(['administrador','recepcionista','medico','paciente','sin_vinculo'] as $rol){
            $id=$f->usuario($rol==='sin_vinculo'?'medico':$rol);
            $q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
            $this->actores[$rol]=['id'=>$id,'rol'=>$rol==='sin_vinculo'?'medico':$rol,'correo'=>$q->fetchColumn()];
        }
        $this->medico=$f->medico($this->actores['medico']['id']);$this->especialidad=$f->especialidades[0];$this->otro=$f->medico();
        $this->paciente=$f->paciente();$otroPaciente=$f->paciente();
        $q=$pdo->prepare("UPDATE pacientes SET nombres=:n,documento_identidad=:ci,telefono='70001234',direccion='DIRECCION_PRIVADA_CU14' WHERE id=:id");
        $q->execute(['n'=>'Paciente <b>ficticio CU14</b>','ci'=>'CI-PRIVADO-'.$f->sufijo,'id'=>$this->paciente]);
        $q=$pdo->prepare("UPDATE pacientes SET nombres='PACIENTE_AGENDA_AJENA_CU14' WHERE id=:id");$q->execute(['id'=>$otroPaciente]);
        $this->t->horario($this->medico);$this->t->horario($this->otro);
        $this->reserva=$this->t->servicio->crear($this->t->datos($this->medico,$this->paciente,$this->especialidad)+['motivo'=>'MOTIVO_PRIVADO_CU14 <script>alert(1)</script>'],$this->actores['administrador']['id'])['id'];
        $this->ajena=$this->t->servicio->crear($this->t->datos($this->otro,$otroPaciente,$f->especialidades[1]),$this->actores['recepcionista']['id'])['id'];
        $insert=$pdo->prepare('INSERT INTO citas(medico_id,paciente_id,especialidad_id,fecha_hora_inicio,estado,motivo) VALUES (:m,:p,:e,:fecha,:estado,:motivo) RETURNING id');
        // Estados históricos ya admitidos por el esquema; no hay acciones de Ciclo 2.
        for($i=0;$i<21;$i++){
            $fecha=(new DateTimeImmutable('2010-01-01 23:30',App\Models\HorarioAtencion::zona()))->modify('+' . $i . ' days');
            $insert->execute(['m'=>$this->medico,'p'=>$this->paciente,'e'=>$this->especialidad,'fecha'=>$fecha->format('c'),'estado'=>'cancelada','motivo'=>'MOTIVO_HISTORICO_PRIVADO_CU14']);$this->canceladas[]=(int)$insert->fetchColumn();
        }
        $insert->execute(['m'=>$this->medico,'p'=>$this->paciente,'e'=>$this->especialidad,'fecha'=>'2011-01-01T00:00:00-04:00','estado'=>'atendida','motivo'=>null]);$this->historica=(int)$insert->fetchColumn();
        $insert->execute(['m'=>$this->medico,'p'=>$this->paciente,'e'=>null,'fecha'=>'1999-12-31T23:30:00-04:00','estado'=>'atendida','motivo'=>null]);$this->legado=(int)$insert->fetchColumn();
    }
    public function consulta(string $rol): App\Services\ConsultaCitaService
    {
        return new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($this->pdo),new App\Repositories\HorarioRepository($this->pdo),$this->actores[$rol]);
    }
}
