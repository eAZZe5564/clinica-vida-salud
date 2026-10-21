<?php // Solo transporta los campos esperados; CitaService vuelve a validarlos. ?>
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_formato" value="html">
<?php foreach(['paciente_id','especialidad_id','medico_id','fecha','hora_inicio','motivo','q'] as $campo): ?><input type="hidden" name="<?= e($campo) ?>" value="<?= e($seleccion[$campo]) ?>"><?php endforeach; ?>
