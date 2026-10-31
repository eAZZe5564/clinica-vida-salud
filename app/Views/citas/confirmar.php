<section class="page-heading"><p class="eyebrow">Confirmación · CU17</p><h1>Confirmar cita</h1><p class="lead"><?= e($cita['codigo']) ?></p></section>
<section class="panel confirmar-panel"><h2>Revisa la cita antes de confirmar</h2>
<dl class="detail-grid">
    <div><dt>Código de cita</dt><dd><?= e($cita['codigo']) ?></dd></div>
    <div><dt>Paciente</dt><dd><?= e($cita['paciente_nombres'].' '.$cita['paciente_apellidos']) ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($cita['medico_nombres'].' '.$cita['medico_apellidos']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($cita['especialidad']??'Sin registrar') ?></dd></div>
    <div><dt>Fecha y hora · America/La_Paz</dt><dd><?= e($cita['horario_actual']) ?></dd></div>
    <div><dt>Estado actual</dt><dd>Programada</dd></div>
</dl>
<p>Todavía no se ha confirmado la cita. Al confirmar, su estado será Confirmada y el horario continuará reservado.</p>
<?php if($errores): ?><div class="notice notice-error" role="alert"><ul><?php foreach($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/citas/<?= (int)$cita['id'] ?>/confirmar" data-confirmar-cita>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="original" value="<?= e($cita['original']) ?>">
    <div class="form-actions"><button class="button" name="accion" value="confirmar" type="submit">Confirmar cita médica</button><a class="button button-secondary" href="/citas/<?= (int)$cita['id'] ?>">Volver sin confirmar</a></div>
</form></section>
