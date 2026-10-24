<?php
declare(strict_types=1);
/** Datos ficticios rastreados por ID; nunca usa ni elimina registros previos. */
final class HorariosFixture
{
    public array $medicos=[];
    public array $pacientes=[];
    public array $especialidades=[];
    public array $usuarios=[];
    public readonly string $sufijo;
    public function __construct(private readonly PDO $pdo) { $this->sufijo=bin2hex(random_bytes(8)); }
    public function medico(?int $usuario=null): int
    {
        $q=$this->pdo->prepare("INSERT INTO especialidades(nombre) VALUES (:nombre) RETURNING id");
        $q->execute(['nombre'=>'Horario ficticio ' . $this->sufijo . '-' . count($this->medicos)]); $e=(int)$q->fetchColumn(); $this->especialidades[]=$e;
        $q=$this->pdo->prepare("INSERT INTO medicos(nombres,apellidos,numero_colegiatura,usuario_id) VALUES (:n,'Prueba horarios',:r,:u) RETURNING id");
        $q->execute(['n'=>'Médico ' . $this->sufijo,'r'=>'HOR-' . $this->sufijo . '-' . count($this->medicos),'u'=>$usuario]);
        $id=(int)$q->fetchColumn(); $this->medicos[]=$id;
        $q=$this->pdo->prepare('INSERT INTO medicos_especialidades(medico_id,especialidad_id) VALUES (:m,:e)'); $q->execute(['m'=>$id,'e'=>$e]);
        return $id;
    }
    public function usuario(string $rol): int
    {
        $repo=new App\Repositories\RolRepository($this->pdo); $roles=[];
        foreach($repo->listar() as $r){$roles[$r['nombre']]=(int)$r['id'];}
        $servicio=new App\Services\UsuarioService($this->pdo,new App\Repositories\UsuarioRepository($this->pdo),$repo);
        $id=$servicio->crear('horario-' . $rol . '-' . $this->sufijo . '-' . count($this->usuarios) . '@example.invalid','Ficticia91!' . $this->sufijo,$roles[$rol]);
        $this->usuarios[]=$id; return $id;
    }
    public function paciente(): int
    {
        $q=$this->pdo->prepare("INSERT INTO pacientes(nombres,apellidos,documento_identidad) VALUES ('Paciente ficticio','Prueba horarios',:ci) RETURNING id");
        $q->execute(['ci'=>'HOR-' . $this->sufijo . '-' . count($this->pacientes)]); $id=(int)$q->fetchColumn(); $this->pacientes[]=$id; return $id;
    }
    public function cita(int $medico,int $paciente,string $fecha): int
    {
        $q=$this->pdo->prepare('INSERT INTO citas(medico_id,paciente_id,fecha_hora_inicio) VALUES (:m,:p,:fecha) RETURNING id');
        $q->execute(['m'=>$medico,'p'=>$paciente,'fecha'=>$fecha]); return (int)$q->fetchColumn();
    }
    public function limpiar(): void
    {
        foreach($this->medicos as $id){
            foreach(['citas','horarios_atencion','medicos_especialidades'] as $tabla){$q=$this->pdo->prepare('DELETE FROM ' . $tabla . ' WHERE medico_id=:id');$q->execute(['id'=>$id]);}
            $q=$this->pdo->prepare('DELETE FROM medicos WHERE id=:id');$q->execute(['id'=>$id]);
        }
        foreach(['pacientes'=>$this->pacientes,'especialidades'=>$this->especialidades,'usuarios'=>$this->usuarios] as $tabla=>$ids){
            $q=$this->pdo->prepare('DELETE FROM ' . $tabla . ' WHERE id=:id');foreach($ids as $id){$q->execute(['id'=>$id]);}
        }
    }
}
