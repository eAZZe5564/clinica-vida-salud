<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

final class MedicoRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function obtener(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM medicos WHERE id=:id'); $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function especialidades(int $id): array
    {
        $q = $this->pdo->prepare('SELECT e.* FROM especialidades e JOIN medicos_especialidades me ON me.especialidad_id=e.id WHERE me.medico_id=:id ORDER BY e.nombre');
        $q->execute(['id' => $id]); return $q->fetchAll();
    }
    public function listar(string $busqueda, ?int $especialidad, string $estado, int $pagina): array
    {
        $where = ' WHERE (m.nombres ILIKE :n OR m.apellidos ILIKE :a OR m.numero_colegiatura ILIKE :r)';
        $patron = '%' . strtr($busqueda, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $params = ['n' => $patron, 'a' => $patron, 'r' => $patron];
        if ($especialidad !== null) {
            $where .= ' AND EXISTS(SELECT 1 FROM medicos_especialidades me WHERE me.medico_id=m.id AND me.especialidad_id=:especialidad)';
            $params['especialidad'] = $especialidad;
        }
        if ($estado === 'activo' || $estado === 'inactivo') { $where .= $estado === 'activo' ? ' AND m.activo' : ' AND NOT m.activo'; }
        $q = $this->pdo->prepare('SELECT count(*) FROM medicos m' . $where); $q->execute($params);
        $total = (int) $q->fetchColumn(); $paginas = max(1, (int) ceil($total / 20)); $pagina = min(max(1, $pagina), $paginas);
        $q = $this->pdo->prepare("SELECT m.id,m.nombres,m.apellidos,m.numero_colegiatura,m.telefono,m.correo_contacto,m.activo,
            COALESCE((SELECT string_agg(e.nombre || CASE WHEN e.activo THEN '' ELSE ' (inactiva)' END, ', ' ORDER BY e.nombre)
                FROM especialidades e JOIN medicos_especialidades me ON me.especialidad_id=e.id WHERE me.medico_id=m.id), '') AS especialidades
            FROM medicos m" . $where . ' ORDER BY m.apellidos,m.nombres,m.id LIMIT 20 OFFSET :offset');
        foreach ($params as $clave => $valor) { $q->bindValue($clave, $valor); }
        $q->bindValue('offset', ($pagina - 1) * 20, PDO::PARAM_INT); $q->execute();
        return ['filas' => $q->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }
    public function registroExiste(string $registro, ?int $id): bool
    {
        $q = $this->pdo->prepare("SELECT 1 FROM medicos WHERE upper(regexp_replace(numero_colegiatura, '[[:space:]]+', '', 'g'))=:registro AND id<>:id LIMIT 1");
        $q->execute(['registro' => $registro, 'id' => $id ?? 0]); return (bool) $q->fetchColumn();
    }
    public function guardar(array $datos, ?int $id): int
    {
        $campos = ['nombres','apellidos','numero_colegiatura','telefono','correo_contacto','usuario_id'];
        $params = array_intersect_key($datos, array_flip($campos));
        $sql = $id === null ? 'INSERT INTO medicos(' . implode(',', $campos) . ') VALUES (:' . implode(',:', $campos) . ') RETURNING id'
            : 'UPDATE medicos SET ' . implode(',', array_map(static fn($c) => "$c=:$c", $campos)) . ',actualizado_en=CURRENT_TIMESTAMP WHERE id=:id RETURNING id';
        if ($id !== null) { $params['id'] = $id; }
        $q = $this->pdo->prepare($sql); $q->execute($params); $resultado = $q->fetchColumn();
        if ($resultado === false) { throw new \OutOfBoundsException(); }
        return (int) $resultado;
    }
    public function cuentasDisponibles(?int $id): array
    {
        $q = $this->pdo->prepare("SELECT u.id,u.correo FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.activo AND r.nombre='medico' AND NOT EXISTS (
                SELECT 1 FROM medicos m WHERE m.usuario_id=u.id AND m.id<>:id)
            ORDER BY u.correo LIMIT 200");
        $q->execute(['id' => $id ?? 0]); return $q->fetchAll();
    }
    public function cuentaValida(int $usuario, ?int $id): bool
    {
        $q = $this->pdo->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.id=:usuario AND u.activo AND r.nombre='medico'
            AND NOT EXISTS(SELECT 1 FROM medicos m WHERE m.usuario_id=u.id AND m.id<>:id) FOR SHARE OF u");
        $q->execute(['usuario' => $usuario, 'id' => $id ?? 0]); return (bool) $q->fetchColumn();
    }
    public function asignar(int $id, array $ids): void
    {
        // Se mantienen las filas seleccionadas y sus fechas; PK evita duplicados.
        $actuales = array_map('intval', array_column($this->especialidades($id), 'id'));
        $quitar = $this->pdo->prepare('DELETE FROM medicos_especialidades WHERE medico_id=:m AND especialidad_id=:e');
        foreach (array_diff($actuales, $ids) as $e) { $quitar->execute(['m' => $id, 'e' => $e]); }
        $agregar = $this->pdo->prepare('INSERT INTO medicos_especialidades(medico_id,especialidad_id) VALUES (:m,:e)');
        foreach (array_diff($ids, $actuales) as $e) { $agregar->execute(['m' => $id, 'e' => $e]); }
        $q = $this->pdo->prepare('UPDATE medicos SET actualizado_en=CURRENT_TIMESTAMP WHERE id=:id'); $q->execute(['id' => $id]);
    }
    public function cambiarEstado(int $id, bool $activo): void
    {
        $q = $this->pdo->prepare('UPDATE medicos SET activo=:activo,actualizado_en=CURRENT_TIMESTAMP WHERE id=:id');
        $q->bindValue('activo', $activo, PDO::PARAM_BOOL); $q->bindValue('id', $id, PDO::PARAM_INT); $q->execute();
    }
}
