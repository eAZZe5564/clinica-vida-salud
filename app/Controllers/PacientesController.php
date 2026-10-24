<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Repositories\PacienteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\PacienteService;
use App\Services\PacienteValidacionException;
use App\Models\BusquedaPaciente;

/** Solicitudes HTTP del módulo; autorización común en todas sus rutas. */
final class PacientesController
{
    private AuthMiddleware $middleware;
    private PacienteRepository $repositorio;
    private PacienteService $servicio;
    private array $actor;

    public function __construct()
    {
        $pdo = Database::connection();
        $this->middleware = new AuthMiddleware(new AuthService(new UsuarioRepository($pdo)));
        $this->actor = $this->middleware->exigirAutenticacion();
        $this->middleware->exigirRoles($this->actor, ['administrador', 'recepcionista']);
        $this->repositorio = new PacienteRepository($pdo);
        $this->servicio = new PacienteService($this->repositorio);
    }

    private function pagina(string $vista, string $titulo, array $datos = []): void
    {
        extract($datos, EXTR_SKIP);
        $usuario = $this->actor;
        $contenido = dirname(__DIR__) . '/Views/pacientes/' . $vista . '.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    private function obtener(int $id): ?array
    {
        try { return $this->servicio->obtener($id); }
        catch (\OutOfBoundsException) {
            http_response_code(404);
            require dirname(__DIR__) . '/Views/errors/404.php';
            return null;
        }
    }

    public function listar(): void
    {
        $busqueda = BusquedaPaciente::normalizar($_GET['q'] ?? '');
        $pagina = $this->numeroPagina('pagina');
        $resultado = $this->repositorio->listar($busqueda, $pagina);
        $this->pagina('index', 'Pacientes', compact('busqueda', 'resultado'));
    }

    private function numeroPagina(string $clave): int
    {
        $valor = $_GET[$clave] ?? '1';
        return is_string($valor) ? max(1, (int) filter_var($valor, FILTER_VALIDATE_INT)) : 1;
    }

    public function formulario(?int $id = null): void
    {
        $paciente = $id === null ? [] : $this->obtener($id);
        if ($paciente === null) { return; }
        $this->pagina('form', $id === null ? 'Registrar paciente' : 'Editar paciente',
            ['paciente' => $paciente, 'id' => $id, 'errores' => []]);
    }

    public function guardar(?int $id = null): void
    {
        $this->middleware->exigirCsrf();
        if ($id !== null && $this->obtener($id) === null) { return; }
        try {
            $guardado = $this->servicio->guardar($_POST, $id);
            \redirigir('/pacientes/' . $guardado . '?mensaje=paciente-guardado');
        } catch (PacienteValidacionException $error) {
            http_response_code(422);
            $paciente = [];
            foreach (['nombres', 'apellidos', 'documento_identidad', 'fecha_nacimiento', 'sexo', 'telefono', 'correo_contacto', 'direccion'] as $campo) {
                $paciente[$campo] = is_string($_POST[$campo] ?? null) ? mb_substr($_POST[$campo], 0, 1000) : '';
            }
            $this->pagina('form', $id === null ? 'Registrar paciente' : 'Editar paciente',
                ['paciente' => $paciente, 'id' => $id, 'errores' => $error->errores]);
        }
    }

    public function detalle(int $id): void
    {
        $paciente = $this->obtener($id);
        if ($paciente === null) { return; }
        $citas = $this->repositorio->citas($id, $this->numeroPagina('citas_pagina'));
        $this->pagina('show', 'Detalle del paciente', compact('paciente', 'citas'));
    }

    public function confirmarEstado(int $id): void
    {
        $paciente = $this->obtener($id);
        if ($paciente !== null) { $this->pagina('status', 'Confirmar cambio de estado', compact('paciente')); }
    }

    public function cambiarEstado(int $id): void
    {
        $this->middleware->exigirCsrf();
        if ($this->obtener($id) === null) { return; }
        if (!is_string($_POST['activo'] ?? null) || !in_array($_POST['activo'], ['0', '1'], true)) {
            http_response_code(422);
            $this->pagina('status', 'Confirmar cambio de estado', [
                'paciente' => $this->servicio->obtener($id),
                'mensaje' => ['tipo' => 'error', 'mensaje' => 'Selecciona un estado válido.']]);
            return;
        }
        $this->servicio->cambiarEstado($id, $_POST['activo'] === '1');
        \redirigir('/pacientes/' . $id . '?mensaje=paciente-estado');
    }
}
