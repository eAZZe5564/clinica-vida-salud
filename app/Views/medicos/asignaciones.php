<section class="page-heading"><p class="eyebrow">Especialidades del médico · CU10</p><h1>Asignar especialidades</h1><p class="lead"><?= e($medico['nombres'] . ' ' . $medico['apellidos']) ?></p></section>
<form class="panel user-form" data-catalog-form data-active="<?= $medico['activo'] ? 'true' : 'false' ?>" method="post" action="/medicos/<?= (int) $id ?>/especialidades">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php require __DIR__ . '/seleccion.php'; ?>
    <div class="form-actions"><button class="button button-secondary" type="submit">Guardar asignaciones</button><a class="button button-quiet" href="/medicos/<?= (int) $id ?>">Cancelar</a></div>
</form>
