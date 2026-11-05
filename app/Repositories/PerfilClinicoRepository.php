<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

/** CU23: columnas explícitas; no escribe citas ni expone datos a reportes. */
final class PerfilClinicoRepository
{
    public function __construct(private readonly PDO $pdo) {}
    private function fila(string $sql,array $p): ?array
    {
        $q=$this->pdo->prepare($sql);$q->execute($p);return $q->fetch()?:null;
    }
    public function actor(int $id,bool $bloquear=false): ?array
    {
        return $this->fila("SELECT u.id,u.activo,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.id=:id".($bloquear?' FOR SHARE OF u':''),['id'=>$id]);
    }
    public function medico(int $usuario,bool $escritura): ?array
    {
        return $this->fila('SELECT id,usuario_id,activo FROM medicos WHERE usuario_id=:u AND activo'.
            ($escritura?' FOR UPDATE':' FOR SHARE'),['u'=>$usuario]);
    }
    public function paciente(int $id,bool $escritura): ?array
    {
        return $this->fila('SELECT id,nombres,apellidos,activo FROM pacientes WHERE id=:id AND activo'.
            ($escritura?' FOR UPDATE':' FOR SHARE'),['id'=>$id]);
    }
    public function relacion(int $paciente,int $medico,string $desde,string $hasta): ?array
    {
        return $this->fila("SELECT id,fecha_hora_inicio,estado FROM citas WHERE paciente_id=:p AND medico_id=:m
            AND fecha_hora_inicio>=CAST(:desde AS timestamptz) AND fecha_hora_inicio<CAST(:hasta AS timestamptz)
            AND estado IN ('programada','confirmada','presente','atendida') ORDER BY fecha_hora_inicio,id LIMIT 1 FOR SHARE",
            ['p'=>$paciente,'m'=>$medico,'desde'=>$desde,'hasta'=>$hasta]);
    }
    public function pacientesAutorizados(int $usuario): array
    {
        // Solo IDs administrativos para navegación; la operación destino revalida todos los permisos.
        $q=$this->pdo->prepare("WITH limites AS (SELECT (clock_timestamp() AT TIME ZONE 'America/La_Paz')::date AS dia)
            SELECT DISTINCT c.paciente_id FROM citas c JOIN medicos m ON m.id=c.medico_id
            JOIN usuarios u ON u.id=m.usuario_id JOIN roles r ON r.id=u.rol_id JOIN pacientes p ON p.id=c.paciente_id
            CROSS JOIN limites l WHERE u.id=:u AND u.activo AND m.activo AND p.activo AND r.nombre='medico'
            AND c.estado IN ('programada','confirmada','presente','atendida')
            AND c.fecha_hora_inicio>=l.dia::timestamp AT TIME ZONE 'America/La_Paz'
            AND c.fecha_hora_inicio<(l.dia+1)::timestamp AT TIME ZONE 'America/La_Paz'");
        $q->execute(['u'=>$usuario]);return array_fill_keys(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),true);
    }
    public function perfil(int $paciente): ?array
    {
        return $this->fila('SELECT paciente_id,grupo_sanguineo,fuente_grupo_sanguineo,observaciones,estado_alergias,
            version,creado_por_usuario_id,actualizado_por_usuario_id,creado_en,actualizado_en FROM perfiles_clinicos WHERE paciente_id=:p',['p'=>$paciente]);
    }
    public function alergias(int $paciente): array
    {
        $q=$this->pdo->prepare('SELECT id,paciente_id,sustancia,reaccion,observaciones,estado,version,creado_por_usuario_id,
            actualizado_por_usuario_id,creado_en,actualizado_en FROM alergias_paciente WHERE paciente_id=:p ORDER BY estado,sustancia,id');
        $q->execute(['p'=>$paciente]);return $q->fetchAll();
    }
    public function alergia(int $paciente,int $id): ?array
    {
        return $this->fila('SELECT id,paciente_id,sustancia,reaccion,observaciones,estado,version,creado_por_usuario_id,
            actualizado_por_usuario_id,creado_en,actualizado_en FROM alergias_paciente WHERE paciente_id=:p AND id=:id',['p'=>$paciente,'id'=>$id]);
    }
    public function guardarPerfil(int $paciente,array $d,int $actor,bool $nuevo): void
    {
        $p=$d+['p'=>$paciente,'actor'=>$actor];
        $sql=$nuevo?'INSERT INTO perfiles_clinicos(paciente_id,grupo_sanguineo,fuente_grupo_sanguineo,observaciones,estado_alergias,creado_por_usuario_id,actualizado_por_usuario_id)
            VALUES (:p,:grupo_sanguineo,:fuente_grupo_sanguineo,:observaciones,:estado_alergias,:actor,:editor)'
            :'UPDATE perfiles_clinicos SET grupo_sanguineo=:grupo_sanguineo,fuente_grupo_sanguineo=:fuente_grupo_sanguineo,
            observaciones=:observaciones,estado_alergias=:estado_alergias,version=version+1,actualizado_por_usuario_id=:actor,actualizado_en=clock_timestamp() WHERE paciente_id=:p';
        if($nuevo){$p['editor']=$actor;}$q=$this->pdo->prepare($sql);$q->execute($p);
    }
    public function marcarAntecedentes(int $paciente,int $actor): void
    {
        if($this->perfil($paciente)===null){$this->guardarPerfil($paciente,['grupo_sanguineo'=>null,'fuente_grupo_sanguineo'=>null,'observaciones'=>null,'estado_alergias'=>'con_registros'],$actor,true);}
        else{$q=$this->pdo->prepare("UPDATE perfiles_clinicos SET estado_alergias='con_registros',version=version+1,
            actualizado_por_usuario_id=:u,actualizado_en=clock_timestamp() WHERE paciente_id=:p AND estado_alergias<>'con_registros'");$q->execute(['u'=>$actor,'p'=>$paciente]);}
    }
    public function guardarAlergia(int $paciente,array $d,int $actor,?int $id): int
    {
        $p=$d+['actor'=>$actor,'p'=>$paciente];
        $sql=$id===null?'INSERT INTO alergias_paciente(paciente_id,sustancia,reaccion,observaciones,estado,creado_por_usuario_id,actualizado_por_usuario_id)
            VALUES (:p,:sustancia,:reaccion,:observaciones,:estado,:actor,:editor) RETURNING id'
            :'UPDATE alergias_paciente SET sustancia=:sustancia,reaccion=:reaccion,observaciones=:observaciones,estado=:estado,
            version=version+1,actualizado_por_usuario_id=:actor,actualizado_en=clock_timestamp() WHERE paciente_id=:p AND id=:id RETURNING id';
        if($id===null){$p['editor']=$actor;}else{$p['id']=$id;}
        $q=$this->pdo->prepare($sql);$q->execute($p);return (int)$q->fetchColumn();
    }
    public function registrarRevision(int $id,string $evento,?string $motivo,int $actor): void
    {
        $q=$this->pdo->prepare('INSERT INTO alergias_historial(alergia_id,version,evento,sustancia,reaccion,observaciones,estado,motivo_cambio,usuario_id)
            SELECT id,version,:evento,sustancia,reaccion,observaciones,estado,:motivo,:actor FROM alergias_paciente WHERE id=:id');
        $q->execute(['evento'=>$evento,'motivo'=>$motivo,'actor'=>$actor,'id'=>$id]);
    }
    public function revisiones(int $paciente,int $id): array
    {
        $q=$this->pdo->prepare('SELECT h.version,h.evento,h.sustancia,h.reaccion,h.observaciones,h.estado,h.motivo_cambio,h.usuario_id,h.cambiado_en
            FROM alergias_historial h JOIN alergias_paciente a ON a.id=h.alergia_id WHERE a.paciente_id=:p AND a.id=:id ORDER BY h.version DESC');
        $q->execute(['p'=>$paciente,'id'=>$id]);return $q->fetchAll();
    }
}
