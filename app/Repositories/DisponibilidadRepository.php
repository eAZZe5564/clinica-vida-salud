<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

/** Solo lectura. Las consultas de ocupación no seleccionan datos de pacientes. */
final class DisponibilidadRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function especialidad(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT id,nombre,activo FROM especialidades WHERE id=:id');
        $q->execute(['id'=>$id]); return $q->fetch() ?: null;
    }
    public function medico(int $id, ?int $especialidad): ?array
    {
        $sql='SELECT m.id,m.nombres,m.apellidos,m.activo FROM medicos m WHERE m.id=:id';
        $params=['id'=>$id];
        if($especialidad!==null){$sql.=' AND EXISTS(SELECT 1 FROM medicos_especialidades me WHERE me.medico_id=m.id AND me.especialidad_id=:especialidad)';$params['especialidad']=$especialidad;}
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetch() ?: null;
    }
    public function especialidades(?int $propio=null): array
    {
        $sql='SELECT e.id,e.nombre FROM especialidades e WHERE e.activo';$params=[];
        if($propio!==null){$sql.=' AND EXISTS(SELECT 1 FROM medicos_especialidades me WHERE me.especialidad_id=e.id AND me.medico_id=:propio)';$params['propio']=$propio;}
        $q=$this->pdo->prepare($sql . ' ORDER BY e.nombre,e.id');$q->execute($params);return $q->fetchAll();
    }
    public function medicos(?int $especialidad, ?int $propio=null): array
    {
        $sql='SELECT m.id,m.nombres,m.apellidos FROM medicos m WHERE m.activo';$params=[];
        if($especialidad!==null){$sql.=' AND EXISTS(SELECT 1 FROM medicos_especialidades me JOIN especialidades e ON e.id=me.especialidad_id WHERE me.medico_id=m.id AND e.id=:especialidad AND e.activo)';$params['especialidad']=$especialidad;}
        if($propio!==null){$sql.=' AND m.id=:propio';$params['propio']=$propio;}
        $q=$this->pdo->prepare($sql . ' ORDER BY m.apellidos,m.nombres,m.id');$q->execute($params);return $q->fetchAll();
    }
    public function ocupaciones(int $medico,string $desde,string $hasta,?int $excluirCita=null): array
    {
        // [inicio,fin): incluye reservas que comenzaron antes del día consultado.
        // Ambos estados mantienen reserva, igual que el predicado GiST de 007.
        $q=$this->pdo->prepare("SELECT fecha_hora_inicio,duracion_minutos FROM citas
            WHERE medico_id=:medico AND estado IN ('programada','confirmada')
            AND fecha_hora_inicio<CAST(:hasta AS timestamptz)
            AND fecha_hora_inicio+make_interval(mins=>duracion_minutos)>CAST(:desde AS timestamptz)
            " . ($excluirCita===null?'':'AND id<>:excluir') . " ORDER BY fecha_hora_inicio");
        $params=['medico'=>$medico,'desde'=>$desde,'hasta'=>$hasta];
        if($excluirCita!==null){$params['excluir']=$excluirCita;}
        $q->execute($params);return $q->fetchAll();
    }
}
