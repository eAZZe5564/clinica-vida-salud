<section class="page-heading"><p class="eyebrow">Catálogo · CU09</p><h1><?= $id === null ? 'Registrar especialidad' : 'Editar especialidad' ?></h1></section>
<?php if ($errores): ?><p class="notice notice-error" role="alert">Revisa los campos señalados.</p><?php endif; ?>
<form class="panel user-form" data-catalog-form method="post" action="<?= $id === null ? '/especialidades' : '/especialidades/' . $id . '/editar' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php foreach (['nombre' => ['Nombre *',100,true], 'descripcion' => ['Descripción (opcional)',250,false]] as $campo => [$etiqueta,$maximo,$obligatorio]): ?>
    <div class="field-group"><label for="<?= e($campo) ?>"><?= e($etiqueta) ?></label><input id="<?= e($campo) ?>" name="<?= e($campo) ?>" maxlength="<?= $maximo ?>" value="<?= e($especialidad[$campo] ?? '') ?>" <?= $obligatorio ? 'required' : '' ?> aria-invalid="<?= isset($errores[$campo]) ? 'true' : 'false' ?>" aria-describedby="error-<?= e($campo) ?>"><span class="field-error" id="error-<?= e($campo) ?>" aria-live="polite"><?= e($errores[$campo] ?? '') ?></span></div>
    <?php endforeach; ?>
    <p class="form-hint">El nombre es único sin distinguir mayúsculas ni espacios repetidos. La nueva especialidad se crea activa.</p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Guardar especialidad</button><a class="button button-quiet" href="/especialidades">Cancelar</a></div>
</form>
