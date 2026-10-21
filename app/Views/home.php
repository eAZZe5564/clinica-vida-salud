<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Config/helpers.php';
$appName = \App\Config\Env::get('APP_NAME', 'Clínica Vida Salud');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Base inicial del sistema web de gestión de citas médicas de Clínica Vida Salud.">
    <title><?= e($appName) ?> | Inicio</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" aria-label="Clínica Vida Salud, inicio">
            <span class="brand-mark" aria-hidden="true">+</span>
            <span>Clínica <strong>Vida Salud</strong></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">
            Menú
        </button>
        <nav id="main-navigation" class="navigation" aria-label="Navegación principal">
            <a href="#estado">Estado del proyecto</a>
            <a href="#alcance">Ciclo 1</a>
            <a href="/login">Iniciar sesión</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">Sistema Web · Ciclo 1</p>
                <h1>Una atención más organizada, empieza aquí.</h1>
                <p class="lead">
                    Base inicial para la gestión de pacientes, profesionales, horarios y citas
                    de Clínica Vida Salud.
                </p>
                <a class="button" href="/login">Ingresar al sistema</a>
            </div>
            <div class="hero-card" aria-label="Estado de ejecución">
                <div class="pulse" aria-hidden="true"></div>
                <p class="card-label">Aplicación</p>
                <p class="card-status">PHP está funcionando</p>
                <p class="card-note">Entorno de desarrollo · <?= e(PHP_VERSION) ?></p>
                <div class="card-divider"></div>
                <p class="card-label">Base de datos</p>
                <p class="card-note">PostgreSQL local · acceso protegido por roles.</p>
            </div>
        </section>

        <section id="estado" class="status-section">
            <div>
                <p class="eyebrow">Punto de partida</p>
                <h2>Proyecto preparado para crecer con orden.</h2>
            </div>
            <p>
                Esta primera etapa deja lista la estructura MVC, el arranque seguro y la
                propuesta de esquema de datos. Los flujos de los casos de uso se incorporarán
                gradualmente en los siguientes pasos del Ciclo 1.
            </p>
        </section>

        <section id="alcance" class="scope-grid" aria-label="Áreas del Ciclo 1">
            <article class="scope-card"><span>01</span><h3>Personas</h3><p>Pacientes, médicos, usuarios y roles.</p></article>
            <article class="scope-card"><span>02</span><h3>Atención</h3><p>Especialidades y horarios médicos.</p></article>
            <article class="scope-card"><span>03</span><h3>Agenda</h3><p>Modelo preparado para reservar citas sin cruces.</p></article>
        </section>
    </main>

    <footer class="site-footer">
        <span>Clínica Vida Salud</span>
        <span>Base del proyecto académico · Ciclo 1</span>
    </footer>
</body>
</html>
