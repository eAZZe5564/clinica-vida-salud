<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

/** CU21: agregados del mismo snapshot SQL; jamás une historial ni la tabla puente. */
final class ReporteCitaRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function opciones(): array
    {
        $m=$this->pdo->prepare('SELECT id,nombres,apellidos FROM medicos ORDER BY apellidos,nombres,id');$m->execute();
        $e=$this->pdo->prepare('SELECT id,nombre FROM especialidades ORDER BY nombre,id');$e->execute();
        return ['medicos'=>$m->fetchAll(),'especialidades'=>$e->fetchAll()];
    }

    public function resumir(array $f): array
    {
        $where='c.fecha_hora_inicio>=CAST(:desde AS timestamptz) AND c.fecha_hora_inicio<CAST(:hasta AS timestamptz)';
        $params=['desde'=>$f['limite_desde'],'hasta'=>$f['limite_hasta']];
        foreach(['medico_id','especialidad_id','estado'] as $campo){
            if($f[$campo]!==''){$where.=' AND c.'.$campo.'=:'.$campo;$params[$campo]=$f[$campo];}
        }
        $dia="(c.fecha_hora_inicio AT TIME ZONE 'America/La_Paz')::date";
        $q=$this->pdo->prepare("SELECT CASE
            WHEN GROUPING(c.estado)=0 THEN 'estado'
            WHEN GROUPING(c.medico_id)=0 THEN 'medico'
            WHEN GROUPING(c.especialidad_id)=0 THEN 'especialidad'
            WHEN GROUPING($dia)=0 THEN 'dia' ELSE 'general' END AS tipo,
            c.estado,c.medico_id,m.nombres,m.apellidos,c.especialidad_id,e.nombre,$dia AS dia,
            count(*) AS total,
            count(*) FILTER (WHERE c.estado='programada') AS programada,
            count(*) FILTER (WHERE c.estado='confirmada') AS confirmada,
            count(*) FILTER (WHERE c.estado='presente') AS presente,
            count(*) FILTER (WHERE c.estado='atendida') AS atendida,
            count(*) FILTER (WHERE c.estado='inasistencia') AS inasistencia,
            count(*) FILTER (WHERE c.estado='cancelada') AS cancelada
            FROM citas c JOIN medicos m ON m.id=c.medico_id
            LEFT JOIN especialidades e ON e.id=c.especialidad_id WHERE $where
            GROUP BY GROUPING SETS ((),(c.estado),(c.medico_id,m.nombres,m.apellidos),
                (c.especialidad_id,e.nombre),($dia))
            ORDER BY tipo,apellidos,nombres,medico_id,nombre,especialidad_id,dia,estado");
        $q->execute($params);return $q->fetchAll();
    }
}
