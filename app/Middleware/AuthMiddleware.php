<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

/** Revalida en cada solicitud la sesión, el estado activo y el rol guardado en PostgreSQL. */
final class AuthMiddleware
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function usuarioActual(): ?array
    {
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }

        $ahora = time();
        $ultimaActividad = (int) ($_SESSION['actividad_en'] ?? 0);
        $autenticadoEn = (int) ($_SESSION['autenticado_en'] ?? 0);
        if (
            $ultimaActividad === 0
            || $autenticadoEn === 0
            || $ahora - $ultimaActividad > 1800
            || $ahora - $autenticadoEn > 28800
        ) {
            $this->invalidarSesion();
            return null;
        }

        $usuario = $this->auth->usuarioActivo((int) $_SESSION['usuario_id']);
        if (!$usuario) {
            $this->invalidarSesion();
            return null;
        }

        $_SESSION['actividad_en'] = $ahora;
        $_SESSION['rol'] = $usuario->rol;
        $_SESSION['correo'] = $usuario->correo;

        return [
            'id' => $usuario->id,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol,
        ];
    }

    public function exigirAutenticacion(): array
    {
        $usuario = $this->usuarioActual();
        if (!$usuario) {
            \redirigir('/login?error=sesion');
        }

        return $usuario;
    }

    public function exigirRol(array $usuario, string $rol): void
    {
        $this->exigirRoles($usuario, [$rol]);
    }

    public function exigirRoles(array $usuario, array $roles): void
    {
        // La autorización se hace en el servidor aunque la navegación oculte el enlace.
        if (!in_array($usuario['rol'], $roles, true)) {
            http_response_code(403);
            require dirname(__DIR__) . '/Views/errors/403.php';
            exit;
        }
    }

    public function exigirCsrf(): void
    {
        // También se rechazan tokens enviados con tipos inesperados, como arrays.
        $token = $_POST['_csrf'] ?? null;
        if (!is_string($token) || !\validar_csrf($token)) {
            http_response_code(419);
            require dirname(__DIR__) . '/Views/errors/419.php';
            exit;
        }
    }

    public function invalidarSesion(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parametros['path'],
                'domain' => $parametros['domain'],
                'secure' => $parametros['secure'],
                'httponly' => $parametros['httponly'],
                'samesite' => $parametros['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
