<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/bootstrap.php';
require_once dirname(__DIR__) . '/app/Config/helpers.php';

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\UsuariosController;
use App\Controllers\PacientesController;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if (preg_match('#^/citas/([1-9][0-9]*)/cancelar$#',$path,$cancelacion)===1) {
        $id=filter_var($cancelacion[1],FILTER_VALIDATE_INT);
        if($id===false){http_response_code(404);require dirname(__DIR__).'/app/Views/errors/404.php';}
        elseif(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');require dirname(__DIR__).'/app/Views/errors/405.php';}
        else{$controlador=new \App\Controllers\CancelacionesController();$method==='GET'?$controlador->formulario($id):$controlador->procesar($id);}
        exit;
    }
    if (preg_match('#^/citas/([1-9][0-9]*)/reprogramar$#',$path,$reprogramacion)===1) {
        $id=filter_var($reprogramacion[1],FILTER_VALIDATE_INT);
        if($id===false){http_response_code(404);require dirname(__DIR__) . '/app/Views/errors/404.php';}
        elseif(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');require dirname(__DIR__) . '/app/Views/errors/405.php';}
        else{$controlador=new \App\Controllers\ReprogramacionesController();$method==='GET'?$controlador->formulario($id):$controlador->procesar($id);}
        exit;
    }
    if ($path === '/citas/nueva' || preg_match('#^/citas/confirmacion/([1-9][0-9]*)$#',$path,$recibo)===1) {
        $controladorCitas=new \App\Controllers\CitasController();
        if ($method!=='GET') { http_response_code(405);header('Allow: GET');require dirname(__DIR__) . '/app/Views/errors/405.php'; }
        elseif ($path==='/citas/nueva') { $controladorCitas->formulario(); }
        else { $idRecibo=filter_var($recibo[1],FILTER_VALIDATE_INT);if($idRecibo===false){http_response_code(404);require dirname(__DIR__) . '/app/Views/errors/404.php';}else{$controladorCitas->confirmacion($idRecibo);} }
        exit;
    }
    if ($path === '/citas') {
        $citas = new \App\Controllers\CitasController();
        if ($method === 'GET') { $citas->listar(); }
        elseif ($method === 'POST') { $citas->crear(); }
        else { http_response_code(405); header('Allow: GET, POST'); require dirname(__DIR__) . '/app/Views/errors/405.php'; }
        exit;
    }
    if (preg_match('#^/citas/([1-9][0-9]*)$#',$path,$partesCita)===1) {
        $citas=new \App\Controllers\CitasController();$idCita=filter_var($partesCita[1],FILTER_VALIDATE_INT);
        if($idCita===false){http_response_code(404);require dirname(__DIR__) . '/app/Views/errors/404.php';}
        elseif($method!=='GET'){http_response_code(405);header('Allow: GET');require dirname(__DIR__) . '/app/Views/errors/405.php';}
        else{$citas->detalle($idCita);}
        exit;
    }
    if ($path === '/disponibilidad') {
        $disponibilidad = new \App\Controllers\DisponibilidadController();
        if ($method !== 'GET') {
            http_response_code(405); header('Allow: GET');
            require dirname(__DIR__) . '/app/Views/errors/405.php';
        } else { $disponibilidad->index(); }
        exit;
    }
    if (\App\Config\RutasHorarios::despachar($path, $method)) { exit; }
    if (\App\Config\RutasCatalogo::despachar($path, $method)) { exit; }
    // Rutas de CU04–CU06. Los cambios solo se aceptan por POST con CSRF.
    if (preg_match('#^/pacientes(?:/(nuevo|[1-9][0-9]*)(?:/(editar|estado))?)?$#', $path, $partes) === 1) {
        $recurso = $partes[1] ?? '';
        $accion = $partes[2] ?? '';
        $permitidos = $recurso === '' || ($recurso !== 'nuevo' && $accion !== '') ? ['GET', 'POST'] : ['GET'];
        if ($recurso === 'nuevo' && $accion !== '') { $permitidos = []; }
        if (!in_array($method, $permitidos, true)) {
            http_response_code($permitidos ? 405 : 404);
            if ($permitidos) { header('Allow: ' . implode(', ', $permitidos)); }
            require dirname(__DIR__) . '/app/Views/errors/' . ($permitidos ? '405' : '404') . '.php';
            exit;
        }
        $controlador = new PacientesController();
        if ($recurso === '') {
            $method === 'GET' ? $controlador->listar() : $controlador->guardar();
        } elseif ($recurso === 'nuevo') {
            $controlador->formulario();
        } else {
            $idPaciente = filter_var($recurso, FILTER_VALIDATE_INT);
            if ($idPaciente === false) {
                http_response_code(404);
                require dirname(__DIR__) . '/app/Views/errors/404.php';
            } elseif ($accion === 'editar') {
                $method === 'GET' ? $controlador->formulario($idPaciente) : $controlador->guardar($idPaciente);
            } elseif ($accion === 'estado') {
                $method === 'GET' ? $controlador->confirmarEstado($idPaciente) : $controlador->cambiarEstado($idPaciente);
            } else {
                $controlador->detalle($idPaciente);
            }
        }
        exit;
    }
    if ($method === 'GET' && ($path === '/' || $path === '/index.php')) {
        (new HomeController())->index();
        exit;
    }

    if ($method === 'GET' && $path === '/login') {
        (new AuthController())->formulario();
        exit;
    }

    if ($method === 'POST' && $path === '/login') {
        (new AuthController())->iniciar();
        exit;
    }

    if ($method === 'POST' && $path === '/logout') {
        (new AuthController())->cerrar();
        exit;
    }

    if ($method === 'GET' && $path === '/dashboard') {
        (new HomeController())->dashboard();
        exit;
    }

    if ($method === 'GET' && $path === '/admin/users') {
        (new UsuariosController())->listar();
        exit;
    }

    if ($method === 'GET' && $path === '/admin/users/new') {
        (new UsuariosController())->nuevo();
        exit;
    }

    if ($method === 'POST' && $path === '/admin/users') {
        (new UsuariosController())->guardar();
        exit;
    }

    if (preg_match('#^/admin/users/([1-9][0-9]*)/edit$#', $path, $coincidencias) === 1) {
        $controller = new UsuariosController();
        $id = (int) $coincidencias[1];
        if ($method === 'GET') {
            $controller->editar($id);
            exit;
        }
        if ($method === 'POST') {
            $controller->guardar($id);
            exit;
        }
    }

    if (
        $method === 'POST'
        && preg_match('#^/admin/users/([1-9][0-9]*)/status$#', $path, $coincidencias) === 1
    ) {
        (new UsuariosController())->cambiarEstado((int) $coincidencias[1]);
        exit;
    }

    if (
        str_starts_with($path, '/admin/')
        || $path === '/logout'
        || $path === '/admin/users'
    ) {
        http_response_code(405);
        header('Allow: GET, POST');
        require dirname(__DIR__) . '/app/Views/errors/405.php';
        exit;
    }

    http_response_code(404);
    require dirname(__DIR__) . '/app/Views/errors/404.php';
} catch (Throwable $error) {
    error_log(sprintf(
        'Error interno de Clínica Vida Salud (%s).',
        get_class($error)
    ));
    http_response_code(500);
    require dirname(__DIR__) . '/app/Views/errors/500.php';
}
