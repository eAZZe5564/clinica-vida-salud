<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\EspecialidadRepository;
use App\Services\EspecialidadService;
use App\Services\CatalogoValidacionException;

final class EspecialidadesController extends CatalogoController
{
    private EspecialidadRepository $repo;
    private EspecialidadService $servicio;
    public function __construct()
    {
        parent::__construct(true); $this->repo = new EspecialidadRepository($this->pdo);
        $this->servicio = new EspecialidadService($this->pdo, $this->repo);
    }
    public function listar(): void
    {
        $busqueda = $this->entrada('q'); $resultado = $this->repo->listar($busqueda, $this->numero('pagina') ?? 1);
        $this->pagina('especialidades/index', 'Especialidades', compact('busqueda','resultado'));
    }
    public function detalle(int $id): void
    {
        $especialidad = $this->servicio->obtener($id);
        $this->pagina('especialidades/show', 'Detalle de especialidad', compact('especialidad'));
    }
    public function formulario(?int $id = null, array $errores = [], ?array $datos = null): void
    {
        $especialidad = $datos ?? ($id === null ? [] : $this->servicio->obtener($id));
        $this->pagina('especialidades/form', $id === null ? 'Registrar especialidad' : 'Editar especialidad', compact('especialidad','id','errores'));
    }
    public function guardar(?int $id = null): void
    {
        $this->escritura();
        try { $id = $this->servicio->guardar($_POST, $id); \redirigir('/especialidades/' . $id . '?mensaje=especialidad-guardada'); }
        catch (CatalogoValidacionException $e) {
            http_response_code(422); $this->formulario($id, $e->errores, $this->datosEnviados(['nombre','descripcion']));
        }
    }
    public function estado(int $id, array $errores = []): void
    {
        $registro = $this->servicio->obtener($id); $tipo = 'especialidades'; $nombre = $registro['nombre'];
        $this->pagina('catalogo/estado', 'Confirmar estado de especialidad', compact('registro','tipo','nombre','errores'));
    }
    public function cambiarEstado(int $id): void
    {
        $this->escritura();
        try { $this->servicio->cambiarEstado($id, $this->estadoEnviado()); \redirigir('/especialidades/' . $id . '?mensaje=especialidad-estado'); }
        catch (CatalogoValidacionException $e) { http_response_code(422); $this->estado($id, $e->errores); }
    }
}
