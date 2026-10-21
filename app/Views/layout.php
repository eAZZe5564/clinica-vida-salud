<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Config/helpers.php';
$appName = \App\Config\Env::get('APP_NAME', 'Clínica Vida Salud');
// CU03 usa $usuario para la cuenta editada; la navegación siempre usa al actor autenticado.
$actor = $usuarioSesion ?? $usuario ?? null;
$mensajes = [
    'horario-guardado' => ['tipo'=>'exito','mensaje'=>'El horario se guardó correctamente.'],
    'horario-estado' => ['tipo'=>'exito','mensaje'=>'El estado del horario se actualizó. Se conservaron los registros y las citas.'],
    'medico-guardado' => ['tipo' => 'exito', 'mensaje' => 'Los datos y especialidades del médico se guardaron correctamente.'],
    'medico-estado' => ['tipo' => 'exito', 'mensaje' => 'El estado del médico se actualizó. Sus relaciones y citas se conservaron.'],
    'especialidad-guardada' => ['tipo' => 'exito', 'mensaje' => 'La especialidad se guardó correctamente.'],
    'especialidad-estado' => ['tipo' => 'exito', 'mensaje' => 'El estado de la especialidad se actualizó.'],
    'paciente-guardado' => ['tipo' => 'exito', 'mensaje' => 'Los datos del paciente se guardaron correctamente.'],
    'paciente-estado' => ['tipo' => 'exito', 'mensaje' => 'El estado del paciente se actualizó. Sus citas se conservaron.'],
    'usuario-creado' => ['tipo' => 'exito', 'mensaje' => 'La cuenta se creó correctamente.'],
    'usuario-actualizado' => ['tipo' => 'exito', 'mensaje' => 'Los cambios se guardaron correctamente.'],
    'usuario-activado' => ['tipo' => 'exito', 'mensaje' => 'La cuenta se activó.'],
    'usuario-desactivado' => ['tipo' => 'exito', 'mensaje' => 'La cuenta se desactivó.'],
    'cierre-sesion' => ['tipo' => 'exito', 'mensaje' => 'Has cerrado sesión correctamente.'],
    'datos-invalidos' => ['tipo' => 'error', 'mensaje' => 'No se pudo guardar. Verifica correo, rol y contraseña; la contraseña nueva debe cumplir los requisitos.'],
    'correo-duplicado' => ['tipo' => 'error', 'mensaje' => 'Ya existe una cuenta con ese correo.'],
    'estado-protegido' => ['tipo' => 'error', 'mensaje' => 'La operación se bloqueó por protección de la cuenta administradora o porque la cuenta ya no existe.'],
];
$claveMensaje = is_string($_GET['mensaje'] ?? null) ? $_GET['mensaje'] : '';
$mensaje = $mensaje ?? $mensajes[$claveMensaje] ?? null;
$nombresRoles = [
    'administrador' => 'Administrador',
    'recepcionista' => 'Recepcionista',
    'medico' => 'Médico',
    'paciente' => 'Paciente',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistema de gestión de Clínica Vida Salud.">
    <title><?= e($titulo . ' | ' . $appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
    <script src="/assets/js/citas.js" defer></script>
</head>
<body class="app-shell">
    <header class="app-header">
        <a class="brand" href="/dashboard" aria-label="Clínica Vida Salud, panel">
            <span class="brand-mark" aria-hidden="true">+</span>
            <span>Clínica <strong>Vida Salud</strong></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">Menú</button>
        <nav id="main-navigation" class="navigation app-navigation" aria-label="Navegación principal">
            <a href="/dashboard">Inicio</a>
            <?php if ($actor && in_array($actor['rol'],['administrador','recepcionista','medico'],true)): ?><a href="/citas"><?= $actor['rol']==='medico'?'Mis citas':'Citas médicas' ?></a><?php endif; ?>
            <?php if ($actor && in_array($actor['rol'],['administrador','recepcionista','medico'],true)): ?><a href="/horarios">Horarios médicos</a><a href="/disponibilidad">Disponibilidad</a><?php endif; ?>
            <?php if ($actor && in_array($actor['rol'], ['administrador', 'recepcionista'], true)): ?>
                <a href="/pacientes">Pacientes</a>
                <a href="/citas/nueva">Programar cita</a>
                <a href="/medicos">Médicos</a>
            <?php endif; ?>
            <?php if ($actor && $actor['rol'] === 'administrador'): ?>
                <a href="/admin/users">Usuarios</a>
                <a href="/especialidades">Especialidades</a>
            <?php endif; ?>
            <?php if ($actor): ?>
                <span class="user-chip"><?= e($nombresRoles[$actor['rol']] ?? 'Usuario') ?> · <?= e($actor['correo']) ?></span>
                <form method="post" action="/logout" class="logout-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="link-button" type="submit">Cerrar sesión</button>
                </form>
            <?php else: ?>
                <a href="/login">Iniciar sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="app-main">
        <?php if ($mensaje): ?>
            <div class="notice notice-<?= e((string) $mensaje['tipo']) ?>" role="status">
                <?= e((string) $mensaje['mensaje']) ?>
            </div>
        <?php endif; ?>
        <?php require $contenido; ?>
    </main>

    <footer class="site-footer">
        <span>Clínica Vida Salud</span>
        <span>Gestión de acceso · Ciclo 1</span>
    </footer>
</body>
</html>
