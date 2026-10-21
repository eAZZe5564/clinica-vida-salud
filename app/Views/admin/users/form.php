<?php

declare(strict_types=1);

$esEdicion = $usuario !== null;
$etiquetasRoles = [
    'administrador' => 'Administrador',
    'recepcionista' => 'Recepcionista',
    'medico' => 'Médico',
    'paciente' => 'Paciente',
];
?>
<section class="page-heading">
    <p class="eyebrow">Administración · CU03</p>
    <h1><?= $esEdicion ? 'Editar cuenta' : 'Crear usuario' ?></h1>
    <p class="lead"><?= $esEdicion ? 'Modifica el correo o rol, o establece una nueva contraseña.' : 'Registra una cuenta con uno de los roles disponibles.' ?></p>
</section>

<form class="panel user-form" method="post" action="<?= $esEdicion ? '/admin/users/' . (int) $usuario['id'] . '/edit' : '/admin/users' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label for="correo">Correo electrónico</label>
    <input id="correo" name="correo" type="email" maxlength="254" autocomplete="email" value="<?= e($usuario['correo'] ?? '') ?>" required>

    <label for="rol_id">Rol de acceso</label>
    <select id="rol_id" name="rol_id" required>
        <option value="">Selecciona un rol</option>
        <?php foreach ($roles as $rol): ?>
            <option value="<?= (int) $rol['id'] ?>" <?= isset($usuario['rol_id']) && (int) $usuario['rol_id'] === (int) $rol['id'] ? 'selected' : '' ?>>
                <?= e($etiquetasRoles[$rol['nombre']] ?? $rol['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="contrasena"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña inicial' ?></label>
    <input id="contrasena" name="contrasena" type="password" minlength="12" maxlength="72" autocomplete="new-password" <?= $esEdicion ? '' : 'required' ?>>
    <p class="form-hint"><?= $esEdicion ? 'Déjala vacía para conservar la contraseña actual.' : 'Entre 12 y 72 bytes; debe incluir letras y números.' ?></p>

    <div class="form-actions">
        <button class="button" type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear cuenta' ?></button>
        <a class="button button-quiet" href="/admin/users">Volver</a>
    </div>
</form>
