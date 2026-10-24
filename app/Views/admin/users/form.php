<?php

declare(strict_types=1);

$esEdicion = $usuario !== null;
$erroresContrasena = $erroresContrasena ?? [];
$rolSeleccionado = $rolEnviado ?? $usuario['rol_id'] ?? null;
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

<?php if($erroresContrasena): ?><div class="notice notice-error" id="password-errors" role="alert"><p>Revisa la contraseña:</p><ul><?php foreach($erroresContrasena as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="panel user-form" method="post" action="<?= $esEdicion ? '/admin/users/' . (int) $usuario['id'] . '/edit' : '/admin/users' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label for="correo">Correo electrónico</label>
    <input id="correo" name="correo" type="email" maxlength="254" autocomplete="email" value="<?= e($correoEnviado ?? $usuario['correo'] ?? '') ?>" required>

    <label for="rol_id">Rol de acceso</label>
    <select id="rol_id" name="rol_id" required>
        <option value="">Selecciona un rol</option>
        <?php foreach ($roles as $rol): ?>
            <option value="<?= (int) $rol['id'] ?>" <?= $rolSeleccionado!==null && (int)$rolSeleccionado === (int) $rol['id'] ? 'selected' : '' ?>>
                <?= e($etiquetasRoles[$rol['nombre']] ?? $rol['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="contrasena"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña inicial' ?></label>
    <input id="contrasena" name="contrasena" type="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help<?= $erroresContrasena?' password-errors':'' ?>" aria-invalid="<?= $erroresContrasena?'true':'false' ?>" <?= $esEdicion ? '' : 'required' ?>>
    <p class="form-hint" id="password-help"><?= $esEdicion ? 'Déjala vacía para conservar la contraseña actual. ' : '' ?><?= e(\App\Services\PoliticaContrasena::AYUDA) ?></p>

    <div class="form-actions">
        <button class="button" type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear cuenta' ?></button>
        <a class="button button-quiet" href="/admin/users">Volver</a>
    </div>
</form>
