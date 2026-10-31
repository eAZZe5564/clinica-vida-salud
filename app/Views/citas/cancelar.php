<section class="page-heading"><p class="eyebrow">Cancelación · CU16</p><h1>Cancelar cita</h1><p class="lead"><?= e($cita['codigo']) ?></p></section>
<section class="panel cancelar-panel"><h2>Confirma la cita que deseas cancelar</h2>
<dl class="detail-grid">
    <div><dt>Código de cita</dt><dd><?= e($cita['codigo']) ?></dd></div>
    <div><dt>Paciente</dt><dd><?= e($cita['paciente_nombres'].' '.$cita['paciente_apellidos']) ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($cita['medico_nombres'].' '.$cita['medico_apellidos']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($cita['especialidad']??'Sin registrar') ?></dd></div>
    <div><dt>Fecha y hora · America/La_Paz</dt><dd><?= e($cita['horario_actual']) ?></dd></div>
    <div><dt>Estado actual</dt><dd>Programada</dd></div>
</dl>
<p>Todavía no se ha cancelado la cita. Al confirmar se conservará su registro con estado Cancelada y el turno podrá volver a reservarse si continúa disponible.</p>
<?php if($errores): ?><div class="notice notice-error" role="alert"><ul><?php foreach($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/citas/<?= (int)$cita['id'] ?>/cancelar" data-cancelar-confirmar>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="original" value="<?= e($cita['original']) ?>">
    <div class="field-group"><label for="cancelar-motivo">Motivo administrativo (opcional)</label><input id="cancelar-motivo" name="motivo_cancelacion" maxlength="250" value="<?= e($motivo) ?>" aria-describedby="cancelar-ayuda"><p class="field-hint" id="cancelar-ayuda">Hasta 250 caracteres. Evita incluir información clínica o datos sensibles.</p></div>
    <div class="form-actions"><button class="button button-danger" name="accion" value="confirmar" type="submit">Confirmar cancelación</button><a class="button button-secondary" href="/citas/<?= (int)$cita['id'] ?>">Volver sin cancelar</a></div>
</form></section>
