<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;

final class AuthController
{
    private AuthMiddleware $middleware;
    private AuthService $auth;

    public function __construct()
    {
        $pdo = Database::connection();
        $this->auth = new AuthService(new UsuarioRepository($pdo));
        $this->middleware = new AuthMiddleware($this->auth);
    }

    public function formulario(): void
    {
        if ($this->middleware->usuarioActual()) {
            \redirigir('/dashboard');
        }

        $titulo = 'Iniciar sesión';
        $mensaje = match ($_GET['error'] ?? '') {
            'credenciales' => [
                'tipo' => 'error',
                'mensaje' => 'No se pudo iniciar sesión. Revisa tus credenciales e inténtalo de nuevo.',
            ],
            'sesion' => [
                'tipo' => 'error',
                'mensaje' => 'Tu sesión terminó. Inicia sesión para continuar.',
            ],
            default => null,
        };
        $contenido = dirname(__DIR__) . '/Views/auth/login.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function iniciar(): void
    {
        $this->middleware->exigirCsrf();
        $correo = $_POST['correo'] ?? null;
        $contrasena = $_POST['contrasena'] ?? null;

        if (
            !is_string($correo)
            || !is_string($contrasena)
            || strlen($correo) > 254
            || trim($correo) === ''
            || $contrasena === ''
            || strlen($contrasena) > 1024
        ) {
            \redirigir('/login?error=credenciales');
        }

        $usuario = $this->auth->autenticar(trim($correo), $contrasena);
        if (!$usuario) {
            \redirigir('/login?error=credenciales');
        }

        session_regenerate_id(true);
        $_SESSION = [
            'usuario_id' => $usuario->id,
            'rol' => $usuario->rol,
            'correo' => $usuario->correo,
            'autenticado_en' => time(),
            'actividad_en' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
        \redirigir('/dashboard');
    }

    public function cerrar(): void
    {
        $this->middleware->exigirCsrf();
        $this->middleware->invalidarSesion();
        \redirigir('/login?mensaje=cierre-sesion');
    }
}
