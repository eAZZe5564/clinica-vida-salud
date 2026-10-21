<fieldset class="specialty-options"><legend>Especialidades del médico</legend>
    <p class="form-hint">Un médico activo necesita al menos una especialidad activa. Las inactivas ya asociadas pueden conservarse o retirarse.</p>
    <?php foreach ($opciones as $opcion): ?>
        <label class="checkbox-option"><input type="checkbox" name="especialidades[]" data-inactive="<?= $opcion['activo'] ? 'false' : 'true' ?>" value="<?= (int) $opcion['id'] ?>" <?= in_array((string) $opcion['id'], array_map('strval', $asignadas), true) ? 'checked' : '' ?>>
        <span><?= e($opcion['nombre']) ?><?= $opcion['activo'] ? '' : ' (inactiva, ya asignada)' ?></span></label>
    <?php endforeach; ?>
    <?php if (!$opciones): ?><p>No hay especialidades activas. Registra o reactiva una especialidad antes de crear médicos.</p><?php endif; ?>
    <span class="field-error" id="error-especialidades" aria-live="polite"><?= e($errores['especialidades'] ?? '') ?></span>
</fieldset>
