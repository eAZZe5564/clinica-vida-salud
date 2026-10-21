<section class="page-heading"><p class="eyebrow">Consulta de atención · CU12</p><h1>Disponibilidad médica</h1><p class="lead">Consulta turnos de 30 minutos en <?= e(\App\Models\HorarioAtencion::zona()->getName()) ?>.</p></section>
<?php if($sinVinculo): ?>
<p class="notice notice-error">Tu cuenta no tiene un registro médico vinculado. No se muestran médicos ni disponibilidad de otras personas.</p>
<?php else: ?>
<?php if($errores): ?><div class="notice notice-error" role="alert">Revisa los datos de consulta.<?php if(isset($errores['consulta'])): ?><p><?= e($errores['consulta']) ?></p><?php endif; ?></div><?php endif; ?>
<form class="panel availability-form" method="get" action="/disponibilidad">
    <div class="field-group"><label for="especialidad_id">Especialidad activa (opcional)</label><select id="especialidad_id" name="especialidad_id" aria-describedby="error-especialidad_id"><option value="">Todas las especialidades</option><?php foreach($especialidades as $e): ?><option value="<?= (int)$e['id'] ?>" <?= $seleccion['especialidad_id']===(string)$e['id']?'selected':'' ?>><?= e($e['nombre']) ?></option><?php endforeach; ?></select><span class="field-error" id="error-especialidad_id"><?= e($errores['especialidad_id']??'') ?></span></div>
    <button class="button button-quiet" type="submit" name="accion" value="filtrar" formnovalidate>Filtrar médicos</button>
    <div class="field-group"><label for="medico_id">Médico activo</label><select id="medico_id" name="medico_id" required aria-describedby="error-medico_id"><option value="">Selecciona un médico</option><?php foreach($opciones as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $seleccion['medico_id']===(string)$m['id']?'selected':'' ?>><?= e($m['apellidos'] . ', ' . $m['nombres']) ?></option><?php endforeach; ?></select><span class="field-error" id="error-medico_id"><?= e($errores['medico_id']??'') ?></span></div>
    <div class="field-group"><label for="fecha">Fecha de atención</label><input id="fecha" name="fecha" type="date" min="<?= e($hoy) ?>" value="<?= e($seleccion['fecha']) ?>" required aria-describedby="error-fecha"><span class="field-error" id="error-fecha"><?= e($errores['fecha']??'') ?></span></div>
    <div class="form-actions"><button class="button button-secondary" type="submit" name="accion" value="consultar">Consultar disponibilidad</button><a class="text-link" href="/disponibilidad">Limpiar</a></div>
</form>
<?php if($resultado!==null): ?>
<section class="panel availability-results" aria-live="polite"><h2 class="catalog-subtitle">Turnos libres · <?= e((new DateTimeImmutable($resultado['fecha']))->format('d/m/Y')) ?></h2><p><?= e($resultado['medico']['nombres'] . ' ' . $resultado['medico']['apellidos']) ?></p>
    <?php if(!$resultado['turnos']): ?><p class="empty-state"><?= $resultado['sin_atencion']?'El médico no tiene atención activa para ese día.':'No quedan turnos disponibles para la fecha consultada.' ?></p>
    <?php else: ?><ul class="availability-slots" aria-label="Turnos disponibles"><?php foreach($resultado['turnos'] as $turno): ?><li data-turno-inicio="<?= e($turno['inicio']) ?>"><time datetime="<?= e($turno['inicio']) ?>"><?= e((new DateTimeImmutable($turno['inicio']))->format('H:i')) ?></time>–<time datetime="<?= e($turno['fin']) ?>"><?= e((new DateTimeImmutable($turno['fin']))->format('H:i')) ?></time><span>Libre</span></li><?php endforeach; ?></ul><?php endif; ?>
    <p class="form-hint">La disponibilidad es informativa y puede cambiar. Al registrar una cita se deberá comprobar nuevamente el turno. Esta pantalla no guarda reservas.</p>
</section>
<?php endif; ?>
<?php endif; ?>
