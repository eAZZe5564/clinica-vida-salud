<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

final class PortalPacienteRepository
{
    public function __construct(private readonly PDO $pdo){}
    public function actor(int $id,bool $bloquear=false): ?array
    {
        $q=$this->pdo->prepare('SELECT u.id,u.activo,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=:id'.($bloquear?' FOR SHARE OF u':''));$q->execute(['id'=>$id]);return $q->fetch()?:null;
    }
    public function paciente(int $actor,bool $bloquear=false): ?array
    {
        $q=$this->pdo->prepare('SELECT id,usuario_id,activo FROM pacientes WHERE usuario_id=:u'.($bloquear?' FOR SHARE':''));$q->execute(['u'=>$actor]);return $q->fetch()?:null;
    }
}
