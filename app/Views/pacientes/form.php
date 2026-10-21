<?php
use App\Services\PacienteService;
$campos = [
    'documento_identidad' => ['CI o documento de identidad', 'text', 40, true],
    'nombres' => ['Nombres', 'text', 100, true],
    'apellidos' => ['Apellidos', 'text', 100, true],
    'fecha_nacimiento' => ['Fecha de nacimiento', 'date', 10, true],
    'telefono' => ['Teléfono (opcional)', 'tel', 30, false],
    'correo_contacto' => ['Correo electrónico (opcional)', 'email', 254, false],
    'direccion' => ['Dirección (opcional)', 'text', 250, false],
];
?>
<section class="page-heading"><p class="eyebrow">Pacientes · <?= $id === null ? 'CU04' : 'CU06' ?></p>
<h1><?= $id === null ? 'Registrar paciente' : 'Editar paciente' ?></h1>
<p class="lead"><?= $id === null ? 'El código se asignará automáticamente. El registro se crea activo.' : 'Código: ' . e(\App\Models\Paciente::codigo($id)) ?>. Los campos marcados con * son obligatorios.</p></section>
<?php if ($errores): ?><div class="notice notice-error" role="alert">Revisa los campos señalados. Se conservaron tus datos.</div><?php endif; ?>
<form class="panel user-form patient-form" data-patient-form method="post" action="<?= $id === null ? '/pacientes' : '/pacientes/' . $id . '/editar' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php foreach ($campos as $campo => [$etiqueta, $tipo, $maximo, $obligatorio]): ?>
    <div class="field-group">
        <label for="<?= e($campo) ?>"><?= e($etiqueta) ?><?= $obligatorio ? ' *' : '' ?></label>
        <input id="<?= e($campo) ?>" name="<?= e($campo) ?>" type="<?= e($tipo) ?>" maxlength="<?= $maximo ?>" value="<?= e((string) ($paciente[$campo] ?? '')) ?>"
            <?= $obligatorio ? 'required' : '' ?> <?= $tipo === 'date' ? 'min="0001-01-01" max="' . PacienteService::hoy() . '"' : '' ?>
            aria-describedby="error-<?= e($campo) ?>" aria-invalid="<?= isset($errores[$campo]) ? 'true' : 'false' ?>">
        <span class="field-error" id="error-<?= e($campo) ?>" aria-live="polite"><?= e($errores[$campo] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
    <div class="field-group">
        <label for="sexo">Sexo *</label>
        <select id="sexo" name="sexo" required aria-describedby="error-sexo" aria-invalid="<?= isset($errores['sexo']) ? 'true' : 'false' ?>">
            <option value="">Selecciona una opción</option>
            <?php foreach (PacienteService::SEXOS as $valor => $etiqueta): ?>
                <option value="<?= e($valor) ?>" <?= ($paciente['sexo'] ?? '') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="field-error" id="error-sexo" aria-live="polite"><?= e($errores['sexo'] ?? '') ?></span>
    </div>
    <p class="form-hint">El CI es obligatorio. Se conservan sus ceros iniciales; se ignoran espacios y mayúsculas para evitar duplicados. No se crea una cuenta de acceso.</p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Guardar paciente</button><a class="button button-quiet" href="<?= $id === null ? '/pacientes' : '/pacientes/' . $id ?>">Cancelar</a></div>
</form>
