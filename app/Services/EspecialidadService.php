<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\EspecialidadRepository;
use PDO;
use PDOException;

final class EspecialidadService
{
    public function __construct(private readonly PDO $pdo, private readonly EspecialidadRepository $repo) {}
    public function validar(array $entrada): array
    {
        $errores = [];
        $nombre = CatalogoValidacion::texto($entrada['nombre'] ?? '', 'nombre', 100, true, $errores);
        $descripcion = CatalogoValidacion::texto($entrada['descripcion'] ?? '', 'descripcion', 250, false, $errores);
        if ($errores) { throw new CatalogoValidacionException($errores); }
        return compact('nombre', 'descripcion');
    }
    public function obtener(int $id): array { return $this->repo->obtener($id) ?? throw new \OutOfBoundsException(); }
    public function guardar(array $entrada, ?int $id = null): int
    {
        $datos = $this->validar($entrada);
        try {
            return CatalogoTransaccion::ejecutar($this->pdo, function () use ($datos, $id): int {
                if ($id !== null) { $this->obtener($id); }
                if ($this->repo->nombreExiste($datos['nombre'], $id)) {
                    throw new CatalogoValidacionException(['nombre' => 'Ya existe una especialidad con ese nombre.']);
                }
                return $this->repo->guardar($datos, $id);
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') { throw new CatalogoValidacionException(['nombre' => 'Ya existe una especialidad con ese nombre.']); }
            throw $e;
        }
    }
    public function cambiarEstado(int $id, bool $activo): void
    {
        CatalogoTransaccion::ejecutar($this->pdo, function () use ($id, $activo): void {
            $this->obtener($id);
            if (!$activo && $this->repo->medicosSinAlternativa($id) > 0) {
                throw new CatalogoValidacionException(['activo' => 'Asigna primero otra especialidad activa a los médicos que dependen de esta, o desactiva esos médicos.']);
            }
            // Conserva el catálogo y todas las asociaciones históricas.
            $this->repo->cambiarEstado($id, $activo);
        });
    }
}
