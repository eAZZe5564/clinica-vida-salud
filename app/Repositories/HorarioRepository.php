<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

/** Consultas de CU11. No elimina horarios ni cambia citas. */
final class HorarioRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function obtener(int $id, bool $bloquear = false): ?array
    {
        $q=$this->pdo->prepare("SELECT h.*,m.nombres,m.apellidos,m.activo AS medico_activo,
            COALESCE((SELECT string_agg(e.nombre, ', ' ORDER BY e.nombre) FROM especialidades e
            JOIN medicos_especialidades me ON me.especialidad_id=e.id WHERE me.medico_id=m.id),'Sin especialidades') AS especialidades
            FROM horarios_atencion h JOIN medicos m ON m.id=h.medico_id WHERE h.id=:id" . ($bloquear ? ' FOR UPDATE OF h' : ''));
        $q->execute(['id'=>$id]); return $q->fetch() ?: null;
    }
    public function medico(int $id, bool $bloquear = false): ?array
    {
        $q=$this->pdo->prepare('SELECT id,nombres,apellidos,activo FROM medicos WHERE id=:id' . ($bloquear ? ' FOR UPDATE' : ''));
        $q->execute(['id'=>$id]); return $q->fetch() ?: null;
    }
    public function vinculado(int $usuario): ?int
    {
        $q=$this->pdo->prepare('SELECT id FROM medicos WHERE usuario_id=:usuario'); $q->execute(['usuario'=>$usuario]);
        $id=$q->fetchColumn(); return $id === false ? null : (int)$id;
    }
    public function medicos(?int $propio = null): array
    {
        $q=$this->pdo->prepare('SELECT id,nombres,apellidos,activo FROM medicos' . ($propio === null ? '' : ' WHERE id=:id') . ' ORDER BY apellidos,nombres,id');
        $q->execute($propio === null ? [] : ['id'=>$propio]); return $q->fetchAll();
    }
    public function listar(?int $medico, ?int $dia, int $pagina): array
    {
        $where=[]; $params=[];
        if ($medico !== null) { $where[]='h.medico_id=:medico'; $params['medico']=$medico; }
        if ($dia !== null) { $where[]='h.dia_semana=:dia'; $params['dia']=$dia; }
        $sqlWhere=$where ? ' WHERE ' . implode(' AND ',$where) : '';
        $q=$this->pdo->prepare('SELECT count(*) FROM horarios_atencion h' . $sqlWhere); $q->execute($params); $total=(int)$q->fetchColumn();
        $paginas=max(1,(int)ceil($total/20)); $pagina=min(max(1,$pagina),$paginas);
        $q=$this->pdo->prepare("SELECT h.*,m.nombres,m.apellidos,m.activo AS medico_activo,
            COALESCE((SELECT string_agg(e.nombre || CASE WHEN e.activo THEN '' ELSE ' (inactiva)' END, ', ' ORDER BY e.nombre)
            FROM especialidades e JOIN medicos_especialidades me ON me.especialidad_id=e.id WHERE me.medico_id=m.id),'Sin especialidades') AS especialidades
            FROM horarios_atencion h JOIN medicos m ON m.id=h.medico_id" . $sqlWhere . ' ORDER BY m.apellidos,m.nombres,m.id,h.dia_semana,h.hora_inicio,h.id LIMIT 20 OFFSET :offset');
        foreach ($params as $campo=>$valor) { $q->bindValue($campo,$valor,PDO::PARAM_INT); }
        $q->bindValue('offset',($pagina-1)*20,PDO::PARAM_INT); $q->execute();
        return ['filas'=>$q->fetchAll(),'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas];
    }
    public function solapa(int $medico, int $dia, string $inicio, string $fin, ?int $id): bool
    {
        // Intervalos semiabiertos [inicio,fin): se permiten tramos consecutivos.
        $q=$this->pdo->prepare('SELECT 1 FROM horarios_atencion WHERE medico_id=:medico AND dia_semana=:dia AND activo AND id<>:id AND hora_inicio<CAST(:fin AS time) AND hora_fin>CAST(:inicio AS time) LIMIT 1');
        $q->execute(['medico'=>$medico,'dia'=>$dia,'inicio'=>$inicio,'fin'=>$fin,'id'=>$id??0]); return (bool)$q->fetchColumn();
    }
    public function guardar(array $datos, ?int $id): int
    {
        $sql=$id === null ? 'INSERT INTO horarios_atencion(medico_id,dia_semana,hora_inicio,hora_fin) VALUES (:medico_id,:dia_semana,:hora_inicio,:hora_fin) RETURNING id'
            : 'UPDATE horarios_atencion SET dia_semana=:dia_semana,hora_inicio=:hora_inicio,hora_fin=:hora_fin WHERE id=:id RETURNING id';
        if ($id !== null) { unset($datos['medico_id']); $datos['id']=$id; }
        $q=$this->pdo->prepare($sql); $q->execute($datos); $guardado=$q->fetchColumn();
        if ($guardado === false) { throw new \OutOfBoundsException(); }
        return (int)$guardado;
    }
    public function cambiarEstado(int $id, bool $activo): void
    {
        $q=$this->pdo->prepare('UPDATE horarios_atencion SET activo=:activo WHERE id=:id');
        $q->bindValue('activo',$activo,PDO::PARAM_BOOL); $q->bindValue('id',$id,PDO::PARAM_INT); $q->execute();
    }
    public function citasSinCobertura(int $medico, array $dias, string $zona): bool
    {
        // Se consulta después del cambio, dentro de la misma transacción.
        // Si una reserva programada queda fuera de un tramo, el servicio revierte.
        $params=['medico'=>$medico,'zona'=>$zona]; $marcas=[];
        foreach (array_values(array_unique($dias)) as $i=>$dia) { $params['dia'.$i]=$dia; $marcas[]=':dia'.$i; }
        $q=$this->pdo->prepare("WITH futuras AS (
            SELECT fecha_hora_inicio AT TIME ZONE :zona AS inicio,duracion_minutos FROM citas
            WHERE medico_id=:medico AND estado='programada' AND fecha_hora_inicio>clock_timestamp()
        ), locales AS (SELECT inicio,inicio+make_interval(mins=>duracion_minutos) AS fin FROM futuras)
        SELECT 1 FROM locales c WHERE extract(isodow FROM c.inicio) IN (" . implode(',',$marcas) . ") AND NOT EXISTS (
            SELECT 1 FROM horarios_atencion h WHERE h.medico_id=:medico_h AND h.activo
            AND h.dia_semana=extract(isodow FROM c.inicio) AND c.inicio::date=c.fin::date
            AND h.hora_inicio<=c.inicio::time AND h.hora_fin>=c.fin::time
            AND mod(extract(epoch FROM (c.inicio::time-h.hora_inicio))::numeric,1800)=0
        ) LIMIT 1");
        $params['medico_h']=$medico; $q->execute($params); return (bool)$q->fetchColumn();
    }
    public function activos(int $medico, int $dia): array
    {
        $q=$this->pdo->prepare('SELECT h.* FROM horarios_atencion h JOIN medicos m ON m.id=h.medico_id WHERE h.medico_id=:medico AND h.dia_semana=:dia AND h.activo AND m.activo ORDER BY h.hora_inicio,h.id');
        $q->execute(['medico'=>$medico,'dia'=>$dia]); return $q->fetchAll();
    }
}
