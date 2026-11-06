<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** CU25: extensión acotada de los servicios existentes, sin suplantar roles. */
final class AccesoCitaPaciente
{
    public static function previo(PDO $pdo,int $actor,int $cita): void
    {
        $q=$pdo->prepare('SELECT r.nombre,u.activo FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=:id');$q->execute(['id'=>$actor]);$u=$q->fetch();
        if(($u['nombre']??null)!=='paciente'){return;}
        $v=$pdo->prepare('SELECT id FROM pacientes WHERE usuario_id=:u AND activo');$v->execute(['u'=>$actor]);
        if(!$u['activo']||!$v->fetchColumn()){throw new CitaException(['permisos'=>'No tienes una vinculación activa para esta operación.'],403);}
        $q=$pdo->prepare('SELECT c.id FROM citas c JOIN pacientes p ON p.id=c.paciente_id WHERE c.id=:c AND p.usuario_id=:u AND p.activo');$q->execute(['c'=>$cita,'u'=>$actor]);
        if(!$q->fetchColumn()){throw new CitaException(['cita'=>'Recurso no disponible.'],404);}
    }
    public static function autorizar(?array $actor,?array $paciente): void
    {
        if(!$actor||!$actor['activo']){throw new CitaException(['permisos'=>'No tienes autorización para esta operación.'],403);}
        if(in_array($actor['rol'],['administrador','recepcionista'],true)){return;}
        if($actor['rol']==='paciente'&&$paciente&&$paciente['activo']&&(int)($paciente['usuario_id']??0)===(int)$actor['id']){return;}
        throw new CitaException(['permisos'=>'No tienes autorización para esta operación.'],403);
    }
}
