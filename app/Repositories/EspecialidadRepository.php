<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

final class EspecialidadRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function obtener(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM especialidades WHERE id=:id');
        $q->execute(['id' => $id]); return $q->fetch() ?: null;
    }
    public function listar(string $busqueda, int $pagina): array
    {
        $patron = '%' . strtr($busqueda, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $where = ' WHERE nombre ILIKE :busqueda';
        $q = $this->pdo->prepare('SELECT count(*) FROM especialidades' . $where);
        $q->execute(['busqueda' => $patron]); $total = (int) $q->fetchColumn();
        $paginas = max(1, (int) ceil($total / 20)); $pagina = min(max(1, $pagina), $paginas);
        $q = $this->pdo->prepare('SELECT * FROM especialidades' . $where . ' ORDER BY nombre,id LIMIT 20 OFFSET :offset');
        $q->bindValue('busqueda', $patron); $q->bindValue('offset', ($pagina - 1) * 20, PDO::PARAM_INT); $q->execute();
        return ['filas' => $q->fetchAll(), 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
    }
    public function opciones(?int $medicoId = null): array
    {
        // Incluye inactivas ya asignadas para conservarlas o retirarlas al editar.
        $q = $this->pdo->prepare('SELECT e.* FROM especialidades e WHERE e.activo
            OR EXISTS(SELECT 1 FROM medicos_especialidades me WHERE me.especialidad_id=e.id AND me.medico_id=:medico)
            ORDER BY e.nombre,e.id');
        $q->execute(['medico' => $medicoId ?? 0]); return $q->fetchAll();
    }
    public function nombreExiste(string $nombre, ?int $id): bool
    {
        $q = $this->pdo->prepare("SELECT 1 FROM especialidades
            WHERE lower(btrim(regexp_replace(nombre, '[[:space:]]+', ' ', 'g'))) = lower(:nombre) AND id<>:id LIMIT 1");
        $q->execute(['nombre' => $nombre, 'id' => $id ?? 0]); return (bool) $q->fetchColumn();
    }
    public function guardar(array $datos, ?int $id): int
    {
        $sql = $id === null ? 'INSERT INTO especialidades(nombre,descripcion) VALUES (:nombre,:descripcion) RETURNING id'
            : 'UPDATE especialidades SET nombre=:nombre,descripcion=:descripcion WHERE id=:id RETURNING id';
        if ($id !== null) { $datos['id'] = $id; }
        $q = $this->pdo->prepare($sql); $q->execute($datos); $resultado = $q->fetchColumn();
        if ($resultado === false) { throw new \OutOfBoundsException(); }
        return (int) $resultado;
    }
    public function medicosSinAlternativa(int $especialidad): int
    {
        $q = $this->pdo->prepare('SELECT count(*) FROM medicos m
            JOIN medicos_especialidades me ON me.medico_id=m.id
            WHERE m.activo AND me.especialidad_id=:id AND NOT EXISTS (
                SELECT 1 FROM medicos_especialidades otra JOIN especialidades e ON e.id=otra.especialidad_id
                WHERE otra.medico_id=m.id AND e.activo AND e.id<>:otra)');
        $q->execute(['id' => $especialidad, 'otra' => $especialidad]); return (int) $q->fetchColumn();
    }
    public function cambiarEstado(int $id, bool $activo): void
    {
        $q = $this->pdo->prepare('UPDATE especialidades SET activo=:activo WHERE id=:id');
        $q->bindValue('activo', $activo, PDO::PARAM_BOOL); $q->bindValue('id', $id, PDO::PARAM_INT); $q->execute();
    }
}
