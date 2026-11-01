<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

/** CU19: lecturas acotadas a un profesional y, para citas, a un máximo de una semana. */
final class AgendaRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function especialidades(int $medico): array
    {
        $q=$this->pdo->prepare('SELECT e.nombre,e.activo FROM especialidades e
            JOIN medicos_especialidades me ON me.especialidad_id=e.id
            WHERE me.medico_id=:medico ORDER BY e.nombre,e.id');
        $q->execute(['medico'=>$medico]);return $q->fetchAll();
    }

    public function horarios(int $medico): array
    {
        $q=$this->pdo->prepare('SELECT dia_semana,hora_inicio,hora_fin FROM horarios_atencion
            WHERE medico_id=:medico AND activo ORDER BY dia_semana,hora_inicio,id');
        $q->execute(['medico'=>$medico]);return $q->fetchAll();
    }

    public function citas(int $medico,string $desde,string $hasta): array
    {
        // La duración está limitada a 30 minutos por el CHECK existente. Se incluye
        // el arrastre anterior solo para no presentar un intervalo ocupado como vacío.
        // No se aplica DATE() a la columna indexada ni se consultan motivos/contactos.
        $q=$this->pdo->prepare("SELECT c.id,c.estado,c.fecha_hora_inicio,c.duracion_minutos,
            p.nombres AS paciente_nombres,p.apellidos AS paciente_apellidos,e.nombre AS especialidad
            FROM citas c JOIN pacientes p ON p.id=c.paciente_id
            LEFT JOIN especialidades e ON e.id=c.especialidad_id
            WHERE c.medico_id=:medico
            AND c.fecha_hora_inicio>=CAST(:desde AS timestamptz)-INTERVAL '30 minutes'
            AND c.fecha_hora_inicio<CAST(:hasta AS timestamptz)
            AND c.fecha_hora_inicio+make_interval(mins=>c.duracion_minutos)>CAST(:solape AS timestamptz)
            ORDER BY c.fecha_hora_inicio,c.id");
        $q->execute(['medico'=>$medico,'desde'=>$desde,'hasta'=>$hasta,'solape'=>$desde]);return $q->fetchAll();
    }
}
