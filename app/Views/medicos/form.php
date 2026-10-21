<section class="page-heading"><p class="eyebrow">Médicos · CU07–CU08</p><h1><?= $id === null ? 'Registrar médico' : 'Editar médico' ?></h1><p class="lead"><?= $id === null ? 'El código se asignará automáticamente y el médico se creará activo.' : e(\App\Models\Medico::codigo($id)) ?> Los campos con * son obligatorios.</p></section>
<?php if ($errores): ?><p class="notice notice-error" role="alert">Revisa los campos señalados.</p><?php endif; ?>
<form class="panel user-form patient-form" data-catalog-form data-active="<?= ($medico['activo'] ?? true) ? 'true' : 'false' ?>" method="post" action="<?= $id === null ? '/medicos' : '/medicos/' . $id . '/editar' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php foreach (['numero_colegiatura' => ['Registro profesional *','text',60,true], 'nombres' => ['Nombres *','text',100,true], 'apellidos' => ['Apellidos *','text',100,true], 'telefono' => ['Teléfono (opcional)','tel',30,false], 'correo_contacto' => ['Correo electrónico (opcional)','email',254,false]] as $campo => [$etiqueta,$tipo,$maximo,$obligatorio]): ?>
    <div class="field-group"><label for="<?= e($campo) ?>"><?= e($etiqueta) ?></label>
        <input id="<?= e($campo) ?>" name="<?= e($campo) ?>" type="<?= e($tipo) ?>" maxlength="<?= $maximo ?>" value="<?= e((string) ($medico[$campo] ?? '')) ?>" <?= $obligatorio ? 'required' : '' ?> aria-invalid="<?= isset($errores[$campo]) ? 'true' : 'false' ?>" aria-describedby="error-<?= e($campo) ?>">
        <span class="field-error" id="error-<?= e($campo) ?>" aria-live="polite"><?= e($errores[$campo] ?? '') ?></span>
    </div><?php endforeach; ?>
    <?php require __DIR__ . '/seleccion.php'; ?>
    <div class="field-group"><label for="usuario_id">Cuenta de acceso existente (opcional)</label>
        <select id="usuario_id" name="usuario_id" aria-describedby="error-usuario_id"><option value="">Sin cuenta vinculada</option>
        <?php foreach ($cuentas as $cuenta): ?><option value="<?= (int) $cuenta['id'] ?>" <?= (string) ($medico['usuario_id'] ?? '') === (string) $cuenta['id'] ? 'selected' : '' ?>><?= e($cuenta['correo']) ?></option><?php endforeach; ?></select>
        <span class="field-error" id="error-usuario_id"><?= e($errores['usuario_id'] ?? '') ?></span>
        <?php if (!empty($medico['usuario_id']) && !in_array((int) $medico['usuario_id'], array_map('intval', array_column($cuentas,'id')),true)): ?><p class="form-hint">La cuenta vinculada ya no está disponible como cuenta médica activa. Selecciona otra o guarda sin vínculo.</p><?php endif; ?>
    </div>
    <p class="form-hint">Teléfono: de 7 a 15 dígitos. Se ofrecen hasta 200 cuentas activas con rol Médico y sin otro médico vinculado. No se crean cuentas automáticamente.</p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Guardar médico</button><a class="button button-quiet" href="<?= $id === null ? '/medicos' : '/medicos/' . $id ?>">Cancelar</a></div>
</form>
