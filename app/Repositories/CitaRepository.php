<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

/** Persistencia de reservas y consultas CU14; no realiza transiciones de estado. */
final class CitaRepository
{
    public function __construct(private readonly PDO $pdo) {}
    private const JOINS = ' FROM citas c JOIN pacientes p ON p.id=c.paciente_id
        JOIN medicos m ON m.id=c.medico_id LEFT JOIN especialidades e ON e.id=c.especialidad_id';
    private const BASICOS = 'c.id,c.estado,c.fecha_hora_inicio,c.duracion_minutos,
        p.nombres AS paciente_nombres,p.apellidos AS paciente_apellidos,
        m.nombres AS medico_nombres,m.apellidos AS medico_apellidos,e.nombre AS especialidad,
        (c.estado=\'programada\' AND m.activo AND p.activo AND COALESCE(e.activo,false)
        AND EXISTS(SELECT 1 FROM medicos_especialidades me WHERE me.medico_id=c.medico_id AND me.especialidad_id=c.especialidad_id)) AS reprogramable';

    /** NULL representa gestión general; 0 representa médico sin vínculo, nunca acceso general. */
    private function restricciones(array $filtros, ?int $propio): array
    {
        $where=[];$params=[];
        if($propio!==null){$where[]='c.medico_id=:propio';$params['propio']=$propio;}
        foreach(['medico_id','especialidad_id','estado','id'] as $campo){
            if(isset($filtros[$campo])&&$filtros[$campo]!==''){$where[]='c.' . $campo . '=:' . $campo;$params[$campo]=$filtros[$campo];}
        }
        // Límites locales convertidos a instantes: no aplicar DATE() sobre la columna indexada.
        foreach(['fecha_desde'=>'>=','fecha_hasta'=>'<'] as $campo=>$operador){
            if(isset($filtros[$campo])){$where[]='c.fecha_hora_inicio ' . $operador . ' :' . $campo;$params[$campo]=$filtros[$campo];}
        }
        if(($filtros['periodo']??'todas')!=='todas'){
            $where[]='c.fecha_hora_inicio ' . ($filtros['periodo']==='proximas'?'>=':'<') . ' :ahora';$params['ahora']=$filtros['ahora'];
        }
        return [$where?' WHERE ' . implode(' AND ',$where):'',$params];
    }
    public function listar(array $filtros, ?int $propio, int $pagina): array
    {
        [$where,$params]=$this->restricciones($filtros,$propio);
        $q=$this->pdo->prepare('SELECT count(*) FROM citas c' . $where);$q->execute($params);$total=(int)$q->fetchColumn();
        $paginas=max(1,(int)ceil($total/20));$pagina=min(max(1,$pagina),$paginas);
        // Orden cerrado del servidor: próximas/todas ascendentes; historial descendente.
        $orden=($filtros['periodo']??'todas')==='historicas'?'DESC':'ASC';
        $q=$this->pdo->prepare('SELECT ' . self::BASICOS . self::JOINS . $where . ' ORDER BY c.fecha_hora_inicio ' . $orden . ',c.id ' . $orden . ' LIMIT 20 OFFSET :offset');
        foreach($params as $campo=>$valor){$q->bindValue($campo,$valor,is_int($valor)?PDO::PARAM_INT:PDO::PARAM_STR);}
        $q->bindValue('offset',($pagina-1)*20,PDO::PARAM_INT);$q->execute();
        return ['filas'=>$q->fetchAll(),'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas];
    }
    public function detalle(int $id, ?int $propio, bool $verMotivo, bool $verCreador): ?array
    {
        [$where,$params]=$this->restricciones(['id'=>$id],$propio);
        // Los campos restringidos ni siquiera se seleccionan para el rol médico.
        $campos=self::BASICOS . ',c.creada_en' . ($verMotivo?',c.motivo':'');
        $join=$verCreador?' LEFT JOIN usuarios u ON u.id=c.creada_por_usuario_id':'';
        if($verCreador){$campos.=',u.correo AS registrada_por';}
        $q=$this->pdo->prepare('SELECT ' . $campos . self::JOINS . $join . $where);$q->execute($params);return $q->fetch()?:null;
    }
    /** Incluye catálogos inactivos e historia de asociaciones retiradas. */
    public function especialidadesConsulta(?int $propio): array
    {
        $where=$propio===null?'':' WHERE EXISTS (SELECT 1 FROM medicos_especialidades me WHERE me.especialidad_id=e.id AND me.medico_id=:asignado)
            OR EXISTS (SELECT 1 FROM citas c WHERE c.especialidad_id=e.id AND c.medico_id=:historico)';
        $q=$this->pdo->prepare('SELECT e.id,e.nombre,e.activo FROM especialidades e' . $where . ' ORDER BY e.nombre,e.id');
        $q->execute($propio===null?[]:['asignado'=>$propio,'historico'=>$propio]);return $q->fetchAll();
    }
    public function bloquearMedico(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT id,activo FROM medicos WHERE id=:id FOR SHARE');$q->execute(['id'=>$id]);return $q->fetch()?:null;
    }
    /** CU15: solo identidad/agenda, sin motivo clínico ni datos de contacto. */
    public function paraReprogramar(int $id,bool $bloquear=false): ?array
    {
        $q=$this->pdo->prepare('SELECT c.id,c.paciente_id,c.medico_id,c.especialidad_id,c.estado,c.fecha_hora_inicio,c.duracion_minutos,
            p.nombres AS paciente_nombres,p.apellidos AS paciente_apellidos,
            m.nombres AS medico_nombres,m.apellidos AS medico_apellidos,e.nombre AS especialidad
            FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN medicos m ON m.id=c.medico_id
            LEFT JOIN especialidades e ON e.id=c.especialidad_id WHERE c.id=:id' . ($bloquear?' FOR UPDATE OF c':''));
        $q->execute(['id'=>$id]);return $q->fetch()?:null;
    }
    public function actualizarHorario(int $id,string $inicio): void
    {
        $q=$this->pdo->prepare('UPDATE citas SET fecha_hora_inicio=:inicio WHERE id=:id');
        $q->execute(['id'=>$id,'inicio'=>$inicio]);
        if($q->rowCount()!==1){throw new \OutOfBoundsException();}
    }
    public function revisionReprogramacion(int $id): string
    {
        $q=$this->pdo->prepare('SELECT COALESCE(max(id),0) FROM citas_historial WHERE cita_id=:id');$q->execute(['id'=>$id]);
        return (string)$q->fetchColumn();
    }
    public function registrarReprogramacion(array $cita,string $inicio,int $actor): void
    {
        $q=$this->pdo->prepare("INSERT INTO citas_historial(cita_id,tipo,inicio_anterior,inicio_nuevo,estado_anterior,estado_nuevo,usuario_id)
            VALUES (:id,'reprogramacion',:anterior,:nuevo,'programada','programada',:actor)");
        $q->execute(['id'=>$cita['id'],'anterior'=>$cita['fecha_hora_inicio'],'nuevo'=>$inicio,'actor'=>$actor]);
    }
    public function bloquearEspecialidad(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT id,activo FROM especialidades WHERE id=:id FOR SHARE');$q->execute(['id'=>$id]);return $q->fetch()?:null;
    }
    public function bloquearAsociacion(int $medico,int $especialidad): bool
    {
        $q=$this->pdo->prepare('SELECT 1 FROM medicos_especialidades WHERE medico_id=:m AND especialidad_id=:e FOR SHARE');$q->execute(['m'=>$medico,'e'=>$especialidad]);return (bool)$q->fetchColumn();
    }
    public function bloquearPaciente(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT id,activo FROM pacientes WHERE id=:id FOR SHARE');$q->execute(['id'=>$id]);return $q->fetch()?:null;
    }
    public function bloquearActor(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT u.id,u.activo,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=:id FOR SHARE OF u');
        $q->execute(['id'=>$id]);return $q->fetch()?:null;
    }
    public function crear(array $datos): array
    {
        // Lista explícita: estado, duración, código y actor no provienen de POST.
        $q=$this->pdo->prepare("INSERT INTO citas(paciente_id,medico_id,especialidad_id,fecha_hora_inicio,duracion_minutos,motivo,creada_por_usuario_id,estado)
            VALUES (:paciente_id,:medico_id,:especialidad_id,:inicio,:duracion,:motivo,:actor,'programada')
            RETURNING id,fecha_hora_inicio,duracion_minutos,estado,creada_en");
        $q->execute($datos);return $q->fetch();
    }
    /** Recibo de una creación del actor; no es un listado general de CU14. */
    public function confirmacion(int $id,int $actor): ?array
    {
        $q=$this->pdo->prepare('SELECT c.id,c.estado,c.fecha_hora_inicio,c.duracion_minutos,
            p.id AS paciente_id,p.nombres AS paciente_nombres,p.apellidos AS paciente_apellidos,
            m.nombres AS medico_nombres,m.apellidos AS medico_apellidos,e.nombre AS especialidad
            FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN medicos m ON m.id=c.medico_id
            LEFT JOIN especialidades e ON e.id=c.especialidad_id
            WHERE c.id=:id AND c.creada_por_usuario_id=:actor');
        $q->execute(['id'=>$id,'actor'=>$actor]);return $q->fetch()?:null;
    }
}
