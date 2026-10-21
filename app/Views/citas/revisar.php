<section class="page-heading"><p class="eyebrow">Revisión previa · CU13</p><h1>Confirmar los datos</h1><p class="lead">Todavía no se ha registrado la cita. Revisa el paciente y el turno antes de confirmar.</p></section>
<section class="panel patient-detail"><dl class="detail-grid">
    <div><dt>Paciente</dt><dd><?= e(\App\Models\Paciente::codigo((int)$paciente['id']) . ' · ' . $paciente['nombres'] . ' ' . $paciente['apellidos']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($especialidad['nombre']) ?></dd></div><div><dt>Médico</dt><dd><?= e($medico['nombres'] . ' ' . $medico['apellidos']) ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e((new DateTimeImmutable($seleccion['fecha']))->format('d/m/Y')) ?></dd></div><div><dt>Turno (America/La_Paz)</dt><dd><?= e((new DateTimeImmutable($turno['inicio']))->format('H:i') . '–' . (new DateTimeImmutable($turno['fin']))->format('H:i')) ?></dd></div>
    </dl><p class="form-hint">Al confirmar se volverá a comprobar que el turno siga disponible.</p>
    <form method="post" action="/citas" data-booking-confirm><?php require __DIR__ . '/campos.php'; ?><input type="hidden" name="accion" value="confirmar"><div class="form-actions"><button class="button button-secondary" type="submit" data-booking-submit>Confirmar y registrar cita</button></div><p class="form-hint" data-booking-loading hidden role="status">Registrando la cita, espera la confirmación…</p></form>
    <form method="post" action="/citas"><?php require __DIR__ . '/campos.php'; ?><input type="hidden" name="accion" value="editar"><div class="form-actions"><button class="button button-quiet" type="submit">Volver y corregir</button></div></form>
</section>
