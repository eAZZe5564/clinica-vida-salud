<section class="page-heading"><p class="eyebrow">Horarios · CU11</p><h1><?= $id===null?'Registrar horario':'Editar horario' ?></h1><p class="lead">Horas locales de <?= e(\App\Models\HorarioAtencion::zona()->getName()) ?>. Todos los campos son obligatorios.</p></section>
<?php if ($errores): ?><div class="notice notice-error" role="alert">Revisa los campos señalados.<?php if (isset($errores['citas'])): ?><p><?= e($errores['citas']) ?></p><?php endif; ?></div><?php endif; ?>
<form class="panel user-form" data-horario-form method="post" action="<?= $id===null?'/horarios':'/horarios/' . $id . '/editar' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="field-group"><label for="medico_id">Médico</label>
        <?php if ($id!==null): ?><input type="hidden" name="medico_id" value="<?= (int)$horario['medico_id'] ?>"><?php endif; ?>
        <select id="medico_id" <?= $id===null?'name="medico_id" required':'disabled' ?> aria-describedby="error-medico_id"><option value="">Selecciona un médico</option><?php foreach ($opciones as $opcion): ?><option value="<?= (int)$opcion['id'] ?>" <?= (string)($horario['medico_id']??'')===(string)$opcion['id']?'selected':'' ?>><?= e($opcion['apellidos'] . ', ' . $opcion['nombres']) ?><?= $opcion['activo']?'':' (inactivo)' ?></option><?php endforeach; ?></select>
        <span class="field-error" id="error-medico_id"><?= e($errores['medico_id']??'') ?></span>
    </div>
    <div class="field-group"><label for="dia_semana">Día</label><select id="dia_semana" name="dia_semana" required aria-describedby="error-dia_semana"><option value="">Selecciona un día</option><?php foreach (\App\Models\HorarioAtencion::DIAS as $numero=>$nombre): ?><option value="<?= $numero ?>" <?= (string)($horario['dia_semana']??'')===(string)$numero?'selected':'' ?>><?= e($nombre) ?></option><?php endforeach; ?></select><span class="field-error" id="error-dia_semana"><?= e($errores['dia_semana']??'') ?></span></div>
    <?php foreach (['hora_inicio'=>'Hora de inicio','hora_fin'=>'Hora final'] as $campo=>$etiqueta): ?>
    <div class="field-group"><label for="<?= e($campo) ?>"><?= e($etiqueta) ?></label><input id="<?= e($campo) ?>" name="<?= e($campo) ?>" type="time" min="00:00" max="23:30" step="1800" required value="<?= e(substr($horario[$campo]??'',0,5)) ?>" aria-describedby="error-<?= e($campo) ?>"><span class="field-error" id="error-<?= e($campo) ?>" aria-live="polite"><?= e($errores[$campo]??'') ?></span></div>
    <?php endforeach; ?>
    <p class="form-hint">Duración fija: 30 minutos. Usa minutos 00 o 30, sin atravesar medianoche. Se permiten horarios consecutivos, como 08:00–12:00 y 12:00–16:00. Los nuevos horarios se crean activos.</p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Guardar horario</button><a class="button button-quiet" href="<?= $id===null?'/horarios':'/horarios/' . $id ?>">Cancelar</a></div>
</form>
