<?php

declare(strict_types=1);

$nombresRoles = [
    'administrador' => 'Administrador',
    'recepcionista' => 'Recepcionista',
    'medico' => 'Médico',
    'paciente' => 'Paciente',
];
?>
<section class="page-heading">
    <p class="eyebrow">Panel personal</p>
    <h1>Hola, <?= e($nombresRoles[$usuario['rol']] ?? 'usuario') ?>.</h1>
    <p class="lead">Has iniciado sesión como <?= e($usuario['correo']) ?>.</p>
</section>

<?php if (in_array($usuario['rol'], ['administrador','recepcionista','medico'], true)): ?>
    <section class="panel dashboard-card patient-dashboard"><h2><?= $usuario['rol']==='medico'?'Mis citas':'Citas médicas' ?></h2><p>Consulta las reservas registradas y filtra por fecha, especialidad o estado.</p><a class="text-link" href="/citas">Consultar citas</a></section>
    <section class="panel dashboard-card patient-dashboard"><h2>Horarios y disponibilidad</h2><p><?= $usuario['rol']==='medico' ? 'Consulta los horarios y turnos libres de tu registro médico vinculado.' : 'Consulta los tramos semanales de atención y los turnos libres de los médicos.' ?></p><a class="text-link" href="/horarios">Consultar horarios</a> · <a class="text-link" href="/disponibilidad">Consultar disponibilidad</a></section>
<?php endif; ?>

<?php if (in_array($usuario['rol'], ['administrador', 'recepcionista'], true)): ?>
    <section class="panel dashboard-card patient-dashboard"><h2>Programar cita</h2><p>Selecciona un paciente, revisa los turnos libres y confirma una reserva.</p><a class="text-link" href="/citas/nueva">Programar cita médica</a></section>
    <section class="panel dashboard-card patient-dashboard">
        <span class="card-number">CU04–CU06</span>
        <h2>Pacientes</h2>
        <p>Registra, busca y actualiza pacientes; conserva sus datos y citas al desactivarlos.</p>
        <a class="text-link" href="/pacientes">Gestionar pacientes →</a>
    </section>
<?php endif; ?>

<?php if ($usuario['rol'] === 'administrador'): ?>
    <section class="panel dashboard-card patient-dashboard"><h2>Médicos y especialidades</h2><p>Administra profesionales y sus especialidades.</p><a class="text-link" href="/medicos">Médicos</a> · <a class="text-link" href="/especialidades">Especialidades</a></section>
    <section class="dashboard-grid">
        <article class="panel dashboard-card">
            <span class="card-number">01</span>
            <h2>Usuarios y roles</h2>
            <p>Registra cuentas, consulta usuarios y administra sus roles y estado.</p>
            <a class="text-link" href="/admin/users">Administrar cuentas <span aria-hidden="true">→</span></a>
        </article>
        <article class="panel dashboard-card dashboard-note">
            <span class="card-number">CICLO 1</span>
            <h2>Acceso por rol</h2>
            <p>Las operaciones administrativas se comprueban en el servidor para cada solicitud.</p>
        </article>
    </section>
<?php else: ?>
    <?php if ($usuario['rol'] === 'recepcionista'): ?><section class="panel dashboard-card patient-dashboard"><h2>Consulta de médicos</h2><p>Busca profesionales por especialidad y estado.</p><a class="text-link" href="/medicos">Consultar médicos</a></section><?php endif; ?>
    <section class="panel dashboard-note">
        <span class="card-number">CICLO 1</span>
        <h2>Sesión activa</h2>
        <p>Tu cuenta tiene el rol <?= e($nombresRoles[$usuario['rol']] ?? 'Usuario') ?>. El menú muestra las operaciones disponibles para tu rol.</p>
    </section>
<?php endif; ?>
