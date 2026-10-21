<?php $inicioCita=(new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(\App\Models\HorarioAtencion::zona());$finCita=$inicioCita->modify('+' . (int)$cita['duracion_minutos'] . ' minutes'); ?>
<section class="page-heading"><p class="eyebrow">Reserva completada · CU13</p><h1>Cita programada</h1><p class="lead">Código <?= e(\App\Models\Cita::codigo((int)$cita['id'])) ?>. La reserva se registró correctamente.</p></section>
<section class="panel patient-detail"><dl class="detail-grid">
    <div><dt>Paciente</dt><dd><?= e(\App\Models\Paciente::codigo((int)$cita['paciente_id']) . ' · ' . $cita['paciente_nombres'] . ' ' . $cita['paciente_apellidos']) ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($cita['medico_nombres'] . ' ' . $cita['medico_apellidos']) ?></dd></div><div><dt>Especialidad</dt><dd><?= e($cita['especialidad']??'Sin registrar') ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e($inicioCita->format('d/m/Y')) ?></dd></div><div><dt>Hora inicial y final (America/La_Paz)</dt><dd><?= e($inicioCita->format('H:i') . '–' . $finCita->format('H:i')) ?></dd></div><div><dt>Estado</dt><dd><?= e($cita['estado']) ?></dd></div>
    </dl><div class="form-actions"><a class="button" href="/citas/nueva">Programar otra cita</a><a class="button button-secondary" href="/citas/<?= (int)$cita['id'] ?>">Ver detalle de cita</a><a class="button button-quiet" href="/citas">Consultar citas</a></div>
</section>
