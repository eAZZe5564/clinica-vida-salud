<section class="page-heading"><p class="eyebrow">Consulta de reservas · CU14</p><h1>Detalle de cita</h1><p class="lead"><?= e($cita['codigo']) ?></p></section>
<section class="panel patient-detail"><dl class="detail-grid">
    <div><dt>Código de cita</dt><dd><?= e($cita['codigo']) ?></dd></div>
    <div><dt>Estado</dt><dd><span class="status-pill appointment-<?= e($cita['estado']) ?>"><?= e(\App\Models\Cita::ESTADOS[$cita['estado']]) ?></span></dd></div>
    <div><dt>Paciente</dt><dd><?= e($cita['paciente_nombres'] . ' ' . $cita['paciente_apellidos']) ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($cita['medico_nombres'] . ' ' . $cita['medico_apellidos']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($cita['especialidad']??'Sin registrar') ?></dd></div>
    <div><dt>Fecha de atención</dt><dd><?= e($cita['fecha']) ?></dd></div>
    <div><dt>Horario (America/La_Paz)</dt><dd><?= e($cita['hora_inicio'] . '–' . $cita['hora_fin']) ?></dd></div>
    <div><dt>Fecha de registro (America/La_Paz)</dt><dd><?= e($cita['registro_local']) ?></dd></div>
    <?php if($verMotivo): ?><div><dt>Motivo breve</dt><dd><?= e($cita['motivo']??'Sin registrar') ?></dd></div><?php endif; ?>
    <?php if($verCreador): ?><div><dt>Registrada por</dt><dd><?= e($cita['registrada_por']??'Sin registrar') ?></dd></div><?php endif; ?>
</dl><div class="form-actions"><a class="button button-secondary" href="<?= e($volver) ?>">Volver al listado</a></div></section>
