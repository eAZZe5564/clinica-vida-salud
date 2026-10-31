<section class="page-heading"><p class="eyebrow">Reprogramación · CU15</p><h1>Revisar reprogramación</h1><p class="lead"><?= e($cita['codigo']) ?></p></section>
<section class="panel reprogramar-panel"><p class="notice">Todavía no se ha modificado la cita. Comprueba ambos horarios antes de confirmar.</p>
<dl class="detail-grid">
    <div><dt>Paciente</dt><dd><?= e($cita['paciente_nombres'] . ' ' . $cita['paciente_apellidos']) ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($cita['medico_nombres'] . ' ' . $cita['medico_apellidos']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($cita['especialidad']) ?></dd></div>
    <div><dt>Horario actual</dt><dd><?= e($cita['horario_actual']) ?></dd></div>
    <div><dt>Nuevo horario solicitado</dt><dd><?= e($inicio->format('d/m/Y H:i') . '–' . $inicio->modify('+30 minutes')->format('H:i')) ?></dd></div>
</dl><p>Zona horaria: America/La_Paz. Se conserva el estado Programada.</p>
<form method="post" action="/citas/<?= (int)$cita['id'] ?>/reprogramar" data-reprogramar-confirmar>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="original" value="<?= e($cita['original']) ?>">
    <input type="hidden" name="fecha" value="<?= e($inicio->format('Y-m-d')) ?>">
    <input type="hidden" name="hora_inicio" value="<?= e($inicio->format('H:i')) ?>">
    <input type="hidden" name="accion" value="confirmar">
    <div class="form-actions"><button class="button" type="submit">Confirmar reprogramación</button><a class="button button-secondary" href="/citas/<?= (int)$cita['id'] ?>/reprogramar?<?= e(http_build_query(['fecha'=>$inicio->format('Y-m-d'),'hora_inicio'=>$inicio->format('H:i')])) ?>">Volver a elegir</a></div>
</form></section>
