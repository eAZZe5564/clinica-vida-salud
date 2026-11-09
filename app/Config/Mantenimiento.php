<?php

declare(strict_types=1);

namespace App\Config;

/** Cierra HTTP antes de abrir sesiones, controladores o conexiones PostgreSQL. */
final class Mantenimiento
{
    public static function activo(?string $valor, string $entorno): bool
    {
        if ($valor === null || trim($valor) === '') {
            return $entorno === 'production';
        }

        // Un valor mal escrito conserva el cierre; no interpreta texto arbitrario como falso.
        return !in_array(strtolower(trim($valor)), ['0', 'false', 'off'], true);
    }

    public static function accesoPruebas(mixed $cabecera, mixed $huella): bool
    {
        return is_string($cabecera) && preg_match('/\A[a-f0-9]{64}\z/D', $cabecera) === 1
            && is_string($huella) && preg_match('/\A[a-f0-9]{64}\z/D', $huella) === 1
            && hash_equals($huella, hash('sha256', $cabecera));
    }

    /** null continúa la aplicación; 200/405/503 terminan antes de su bootstrap. */
    public static function respuesta(array $servidor, ?string $modo, string $entorno, ?string $huella): ?int
    {
        $uri = $servidor['REQUEST_URI'] ?? '/';
        $path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : false;
        $metodo = $servidor['REQUEST_METHOD'] ?? '';
        $salud = $path === '/health';

        if ($salud && in_array($metodo, ['GET', 'HEAD'], true)) {
            return 200;
        }
        if (self::activo($modo, $entorno)
            && !self::accesoPruebas($servidor['HTTP_X_CLINICA_MAINTENANCE_KEY'] ?? null, $huella)) {
            return 503;
        }

        return $salud ? 405 : null;
    }

    public static function interceptar(): void
    {
        // Los asistentes CLI mantienen sus propias guardas y autorizaciones.
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }

        require_once __DIR__ . '/Env.php';
        try {
            Env::load(dirname(__DIR__, 2) . '/.env');
            $estado = self::respuesta($_SERVER, Env::get('APP_MAINTENANCE'),
                Env::get('APP_ENV', 'local') ?? 'local', Env::get('APP_MAINTENANCE_TEST_HASH'));
        } catch (\Throwable) {
            // No mostrar la excepción ni abrir la aplicación si su configuración falla.
            $estado = 503;
        }
        if ($estado === null) {
            return;
        }

        http_response_code($estado);
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        if ($estado === 200) {
            header('Content-Type: application/json; charset=UTF-8');
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
                echo '{"status":"ok"}';
            }
        } elseif ($estado === 405) {
            header('Allow: GET, HEAD');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            header('Retry-After: 120');
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
                echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mantenimiento</title><body><main><h1>Mantenimiento temporal</h1><p>El sistema no está disponible en este momento. Intenta nuevamente más tarde.</p></main></body></html>';
            }
        }
        exit;
    }
}
