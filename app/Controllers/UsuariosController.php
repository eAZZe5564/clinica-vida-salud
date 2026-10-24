<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use App\Services\UsuarioService;
use App\Services\ContrasenaValidacionException;
use DomainException;
use PDOException;

final class UsuariosController
{
    private UsuarioService $usuarios;
    private AuthMiddleware $middleware;
    private array $actor;

    public function __construct()
    {
        $pdo = Database::connection();
        $repositorio = new UsuarioRepository($pdo);
        $this->usuarios = new UsuarioService($pdo, $repositorio, new RolRepository($pdo));
        $this->middleware = new AuthMiddleware(
            new \App\Services\AuthService($repositorio)
        );
        $this->actor = $this->middleware->exigirAutenticacion();
        $this->middleware->exigirRol($this->actor, 'administrador');
    }

    public function listar(): void
    {
        $entradaBusqueda = $_GET['q'] ?? '';
        $busqueda = is_string($entradaBusqueda) ? trim($entradaBusqueda) : '';
        if (strlen($busqueda) > 100) {
            $busqueda = substr($busqueda, 0, 100);
        }

        $cuentas = $this->usuarios->listar($busqueda);
        $usuarioSesion = $this->actor;
        $titulo = 'Administrar usuarios';
        $contenido = dirname(__DIR__) . '/Views/admin/users/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function nuevo(): void
    {
        $this->formulario();
    }

    public function editar(int $id): void
    {
        $this->formulario($id);
    }

    /** Nunca conserva el valor del campo contraseña al volver a presentar un error. */
    private function formulario(?int $id=null,array $erroresContrasena=[],?string $correoEnviado=null,?int $rolEnviado=null): void
    {
        $usuarioSesion = $this->actor;
        try {
            $usuario = $id===null?null:$this->usuarios->obtener($id);
        } catch (DomainException) {
            http_response_code(404);
            require dirname(__DIR__) . '/Views/errors/404.php';
            return;
        }
        $roles = $this->usuarios->roles();
        $titulo = $id===null?'Crear usuario':'Editar usuario';
        $contenido = dirname(__DIR__) . '/Views/admin/users/form.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function guardar(?int $id = null): void
    {
        $this->middleware->exigirCsrf();
        $correo = $_POST['correo'] ?? null;
        $contrasena = $_POST['contrasena'] ?? null;
        $entradaRol = $_POST['rol_id'] ?? null;
        $rolId = is_string($entradaRol)
            ? filter_var($entradaRol, FILTER_VALIDATE_INT)
            : false;

        try {
            if (!is_string($correo) || !is_string($contrasena)) {
                throw new DomainException('Los datos enviados no son válidos.');
            }

            if ($rolId === false || $rolId === null) {
                throw new DomainException('Selecciona un rol válido.');
            }

            if ($id === null) {
                $this->usuarios->crear($correo, $contrasena, (int) $rolId);
                \redirigir('/admin/users?mensaje=usuario-creado');
            } else {
                $this->usuarios->actualizar(
                    $id,
                    $correo,
                    (int) $rolId,
                    $contrasena
                );
                \redirigir('/admin/users?mensaje=usuario-actualizado');
            }
        } catch (ContrasenaValidacionException $error) {
            http_response_code(422);
            $this->formulario($id,$error->errores,$correo,is_int($rolId)?$rolId:null);
            return;
        } catch (DomainException $error) {
            $destino = $id === null ? '/admin/users/new' : '/admin/users/' . $id . '/edit';
            \redirigir($destino . '?mensaje=datos-invalidos');
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                $destino = $id === null ? '/admin/users/new' : '/admin/users/' . $id . '/edit';
                \redirigir($destino . '?mensaje=correo-duplicado');
            }
            throw $error;
        }

        \redirigir('/admin/users');
    }

    public function cambiarEstado(int $id): void
    {
        $this->middleware->exigirCsrf();
        $estado = $_POST['activo'] ?? null;
        if (!is_string($estado) || !in_array($estado, ['0', '1'], true)) {
            \redirigir('/admin/users?mensaje=datos-invalidos');
        }
        $activo = $estado === '1';

        try {
            $this->usuarios->cambiarEstado($id, $this->actor['id'], $activo);
            \redirigir('/admin/users?mensaje=' . ($activo ? 'usuario-activado' : 'usuario-desactivado'));
        } catch (DomainException $error) {
            \redirigir('/admin/users?mensaje=estado-protegido');
        }
    }
}
