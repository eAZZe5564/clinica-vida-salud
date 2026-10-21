<?php

declare(strict_types=1);

require_once __DIR__ . '/Env.php';

use App\Config\Env;

Env::load(dirname(__DIR__, 2) . '/.env');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC');
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    $isProduction = Env::get('APP_ENV', 'local') === 'production';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isProduction || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; script-src \'self\'; img-src \'self\' data:; form-action \'self\'; frame-ancestors \'none\'; base-uri \'self\'');
header('Cache-Control: no-store');
