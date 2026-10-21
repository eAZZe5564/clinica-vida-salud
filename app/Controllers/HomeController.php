<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    public function index(): void
    {
        $usuario = $this->middleware()->usuarioActual();
        if ($usuario) {
            \redirigir('/dashboard');
        }

        require dirname(__DIR__) . '/Views/home.php';
    }

    public function dashboard(): void
    {
        $usuario = $this->middleware()->exigirAutenticacion();
        $titulo = 'Panel de inicio';
        $contenido = dirname(__DIR__) . '/Views/dashboard.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    private function middleware(): \App\Middleware\AuthMiddleware
    {
        $pdo = \App\Config\Database::connection();
        return new \App\Middleware\AuthMiddleware(
            new \App\Services\AuthService(new \App\Repositories\UsuarioRepository($pdo))
        );
    }
}
