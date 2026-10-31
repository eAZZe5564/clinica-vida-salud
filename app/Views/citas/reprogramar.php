<section class="page-heading"><p class="eyebrow">Reprogramación · CU15</p><h1>Reprogramar cita</h1><p class="lead"><?= e($cita['codigo']) ?></p></section>
<section class="panel reprogramar-panel"><h2>Horario actual</h2>
<dl class="detail-grid">
    <div><dt>Paciente</dt><dd><?= e($cita['paciente_nombres'] . ' ' . $cita['paciente_apellidos']) ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($cita['medico_nombres'] . ' ' . $cita['medico_apellidos']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($cita['especialidad']) ?></dd></div>
    <div><dt>Estado</dt><dd>Programada</dd></div>
    <div><dt>Fecha y hora · America/La_Paz</dt><dd data-horario-actual><?= e($cita['horario_actual']) ?></dd></div>
</dl><p>Se conserva la misma cita, paciente, médico y especialidad. Duración: 30 minutos.</p></section>
<section class="panel reprogramar-panel"><h2>Nuevo horario solicitado</h2>
<?php if($errores): ?><div class="notice notice-error" role="alert"><ul><?php foreach($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/citas/<?= (int)$cita['id'] ?>/reprogramar" data-reprogramar>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="original" value="<?= e($cita['original']) ?>">
    <div class="field-group"><label for="reprogramar-fecha">Nueva fecha</label><input type="date" id="reprogramar-fecha" name="fecha" min="<?= e($hoy) ?>" value="<?= e($fecha) ?>" required></div>
    <button class="button button-secondary" name="accion" value="actualizar" type="submit">Consultar turnos</button>
    <fieldset class="reprogramar-turnos"><legend>Turnos disponibles · America/La_Paz</legend>
    <div class="reprogramar-grid">
    <?php foreach($disponibles['turnos']??[] as $turno): $inicio=new \DateTimeImmutable($turno['inicio']);$fin=new \DateTimeImmutable($turno['fin']);$actual=$inicio==new \DateTimeImmutable($cita['fecha_hora_inicio']); ?>
    <label class="reprogramar-turno"><input type="radio" name="hora_inicio" value="<?= e($inicio->format('H:i')) ?>" <?= $hora===$inicio->format('H:i')?'checked':'' ?>> <span><?= e($inicio->format('H:i') . '–' . $fin->format('H:i')) ?><?= $actual?' (horario actual)':'' ?></span></label>
    <?php endforeach; ?></div>
    <?php if(empty($disponibles['turnos'])): ?><p>No hay turnos disponibles para esta fecha dentro de los horarios activos.</p><?php endif; ?>
    </fieldset>
    <p class="field-hint">Consulta los turnos después de cambiar la fecha. La disponibilidad se comprobará de nuevo al guardar.</p>
    <div class="form-actions"><button class="button" name="accion" value="revisar" type="submit">Revisar cambio</button><a class="button button-secondary" href="/citas/<?= (int)$cita['id'] ?>">Volver al detalle</a></div>
</form></section>
