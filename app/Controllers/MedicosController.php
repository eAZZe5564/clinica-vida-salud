<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\MedicoRepository;
use App\Repositories\EspecialidadRepository;
use App\Services\MedicoService;
use App\Services\CatalogoValidacionException;

final class MedicosController extends CatalogoController
{
    private MedicoRepository $repo;
    private EspecialidadRepository $especialidades;
    private MedicoService $servicio;
    public function __construct()
    {
        parent::__construct(false);
        $this->repo = new MedicoRepository($this->pdo);
        $this->especialidades = new EspecialidadRepository($this->pdo);
        $this->servicio = new MedicoService($this->pdo, $this->repo, $this->especialidades);
    }
    public function listar(): void
    {
        $busqueda = $this->entrada('q'); $estado = $this->entrada('estado'); $filtro = $this->numero('especialidad');
        $resultado = $this->repo->listar($busqueda, $filtro, $estado, $this->numero('pagina') ?? 1);
        // El filtro conserva especialidades inactivas existentes, sin habilitar nuevas asignaciones.
        $opciones = $this->pdo->query('SELECT id,nombre,activo FROM especialidades ORDER BY nombre')->fetchAll();
        $this->pagina('medicos/index', 'Médicos', compact('busqueda','estado','filtro','resultado','opciones'));
    }
    public function detalle(int $id): void
    {
        $medico = $this->servicio->obtener($id); $asignadas = $this->repo->especialidades($id);
        $horarios = (new \App\Repositories\HorarioRepository($this->pdo))->listar($id,null,$this->numero('horarios_pagina')??1);
        $this->pagina('medicos/show', 'Detalle del médico', compact('medico','asignadas','horarios'));
    }
    public function formulario(?int $id = null, array $errores = [], ?array $datos = null): void
    {
        $this->administrador();
        $medico = $datos ?? ($id === null ? [] : $this->servicio->obtener($id));
        if ($datos !== null && $id !== null) { $medico['activo'] = $this->servicio->obtener($id)['activo']; }
        $asignadas = $datos === null ? ($id === null ? [] : array_column($this->repo->especialidades($id), 'id')) : $this->seleccionEnviada();
        $opciones = $this->especialidades->opciones($id);
        $cuentas = $this->repo->cuentasDisponibles($id);
        $this->pagina('medicos/form', $id === null ? 'Registrar médico' : 'Editar médico', compact('medico','id','errores','opciones','asignadas','cuentas'));
    }
    private function seleccionEnviada(): array
    {
        return is_array($_POST['especialidades'] ?? null) ? array_values(array_filter($_POST['especialidades'], static fn($v) => is_scalar($v))) : [];
    }
    public function guardar(?int $id = null): void
    {
        $this->escritura();
        try { $id = $this->servicio->guardar($_POST, $id); \redirigir('/medicos/' . $id . '?mensaje=medico-guardado'); }
        catch (CatalogoValidacionException $e) {
            http_response_code(422);
            $this->formulario($id, $e->errores, $this->datosEnviados(['nombres','apellidos','numero_colegiatura','telefono','correo_contacto','usuario_id']));
        }
    }
    public function asignaciones(int $id, array $errores = [], bool $enviado = false): void
    {
        $this->administrador(); $medico = $this->servicio->obtener($id);
        $asignadas = $enviado ? $this->seleccionEnviada() : array_column($this->repo->especialidades($id), 'id');
        $opciones = $this->especialidades->opciones($id);
        $this->pagina('medicos/asignaciones', 'Asignar especialidades', compact('medico','id','errores','opciones','asignadas'));
    }
    public function asignar(int $id): void
    {
        $this->escritura();
        try { $this->servicio->asignar($id, $_POST['especialidades'] ?? []); \redirigir('/medicos/' . $id . '?mensaje=medico-guardado'); }
        catch (CatalogoValidacionException $e) { http_response_code(422); $this->asignaciones($id, $e->errores, true); }
    }
    public function estado(int $id, array $errores = []): void
    {
        $this->administrador(); $registro = $this->servicio->obtener($id); $tipo = 'medicos';
        $nombre = $registro['nombres'] . ' ' . $registro['apellidos'];
        $this->pagina('catalogo/estado', 'Confirmar estado del médico', compact('registro','tipo','nombre','errores'));
    }
    public function cambiarEstado(int $id): void
    {
        $this->escritura();
        try { $this->servicio->cambiarEstado($id, $this->estadoEnviado()); \redirigir('/medicos/' . $id . '?mensaje=medico-estado'); }
        catch (CatalogoValidacionException $e) { http_response_code(422); $this->estado($id, $e->errores); }
    }
}
