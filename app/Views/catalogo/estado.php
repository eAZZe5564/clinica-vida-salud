<section class="page-heading"><p class="eyebrow">Catálogo médico</p><h1>Confirmar <?= $registro['activo'] ? 'desactivación' : 'reactivación' ?></h1></section>
<form class="panel user-form" method="post" action="/<?= e($tipo) ?>/<?= (int) $registro['id'] ?>/estado">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="activo" value="<?= $registro['activo'] ? '0' : '1' ?>">
    <?php foreach ($errores as $error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endforeach; ?>
    <p>Vas a <?= $registro['activo'] ? 'desactivar' : 'reactivar' ?>: <strong><?= e($nombre) ?></strong>.</p>
    <p>Se conservarán los datos, asociaciones y citas. <?= $tipo === 'especialidades' ? 'Una especialidad inactiva no admite nuevas asignaciones.' : 'Un médico inactivo no puede recibir nuevas reservas.' ?></p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Confirmar <?= $registro['activo'] ? 'desactivación' : 'reactivación' ?></button><a class="button button-quiet" href="/<?= e($tipo) ?>/<?= (int) $registro['id'] ?>">Cancelar</a></div>
</form>
