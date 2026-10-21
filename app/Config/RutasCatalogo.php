<?php
declare(strict_types=1);
namespace App\Config;
use App\Controllers\MedicosController;
use App\Controllers\EspecialidadesController;

/** Despacho de CU07–CU10; mantiene la lógica fuera del controlador frontal. */
final class RutasCatalogo
{
    public static function despachar(string $path, string $metodo): bool
    {
        if (!preg_match('#^/(medicos|especialidades)(?:/(nuevo|[1-9][0-9]*)(?:/(editar|estado|especialidades))?)?$#', $path, $p)) { return false; }
        $tipo = $p[1]; $recurso = $p[2] ?? ''; $accion = $p[3] ?? '';
        if (($recurso === 'nuevo' && $accion !== '') || ($tipo === 'especialidades' && $accion === 'especialidades')) {
            http_response_code(404); require dirname(__DIR__) . '/Views/errors/404.php'; return true;
        }
        $permitidos = $recurso === '' || $accion !== '' ? ['GET','POST'] : ['GET'];
        if (!in_array($metodo, $permitidos, true)) {
            http_response_code(405); header('Allow: ' . implode(', ', $permitidos));
            require dirname(__DIR__) . '/Views/errors/405.php'; return true;
        }
        $c = $tipo === 'medicos' ? new MedicosController() : new EspecialidadesController();
        try {
            if ($recurso === '') { $metodo === 'GET' ? $c->listar() : $c->guardar(); }
            elseif ($recurso === 'nuevo') { $c->formulario(); }
            else {
                $id = filter_var($recurso, FILTER_VALIDATE_INT);
                if ($id === false) { throw new \OutOfBoundsException(); }
                if ($accion === 'editar') { $metodo === 'GET' ? $c->formulario($id) : $c->guardar($id); }
                elseif ($accion === 'estado') { $metodo === 'GET' ? $c->estado($id) : $c->cambiarEstado($id); }
                elseif ($accion === 'especialidades') { $metodo === 'GET' ? $c->asignaciones($id) : $c->asignar($id); }
                else { $c->detalle($id); }
            }
        } catch (\OutOfBoundsException) { http_response_code(404); require dirname(__DIR__) . '/Views/errors/404.php'; }
        return true;
    }
}
