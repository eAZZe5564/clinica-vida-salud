<section class="page-heading"><p class="eyebrow"><?= e(\App\Models\Paciente::codigo((int) $paciente['id'])) ?> · CU05</p><h1><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></h1></section>
<section class="panel patient-detail">
    <dl class="detail-grid">
    <?php foreach (['documento_identidad' => 'CI / Documento', 'fecha_nacimiento' => 'Fecha de nacimiento', 'sexo' => 'Sexo', 'telefono' => 'Teléfono', 'correo_contacto' => 'Correo', 'direccion' => 'Dirección'] as $campo => $etiqueta): ?>
        <div><dt><?= e($etiqueta) ?></dt><dd><?= e((string) ($campo === 'sexo' ? (\App\Services\PacienteService::SEXOS[$paciente[$campo] ?? ''] ?? 'Sin registrar') : ($paciente[$campo] ?? 'Sin registrar'))) ?></dd></div>
    <?php endforeach; ?>
        <div><dt>Estado</dt><dd><?= $paciente['activo'] ? 'Activo' : 'Inactivo' ?></dd></div>
    </dl>
    <div class="form-actions"><a class="button" href="/pacientes/<?= (int) $paciente['id'] ?>/editar">Editar datos</a><a class="button button-quiet" href="/pacientes/<?= (int) $paciente['id'] ?>/estado"><?= $paciente['activo'] ? 'Desactivar' : 'Reactivar' ?></a><a class="text-link" href="/pacientes">Volver al listado</a></div>
</section>
<section class="panel table-panel patient-appointments"><h2>Citas registradas</h2><p class="form-hint">Consulta de solo lectura. Se muestran todas las citas, incluidas las anteriores.</p>
<?php if (!$citas['filas']): ?><p class="empty-state">Este paciente todavía no tiene citas registradas.</p>
<?php else: ?>
    <div class="table-scroll"><table><thead><tr><th>Fecha y hora (La Paz)</th><th>Médico</th><th>Estado</th></tr></thead><tbody>
    <?php foreach ($citas['filas'] as $cita): ?><tr>
        <td data-label="Fecha y hora"><?= e((new DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(new DateTimeZone('America/La_Paz'))->format('d/m/Y H:i')) ?></td>
        <td data-label="Médico"><?= e($cita['nombres'] . ' ' . $cita['apellidos']) ?></td><td data-label="Estado"><?= e($cita['estado']) ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
    <nav class="pagination" aria-label="Páginas de citas">
    <?php if ($citas['pagina'] > 1): ?><a class="text-link" href="?citas_pagina=<?= $citas['pagina'] - 1 ?>">← Anterior</a><?php endif; ?>
    <span>Página <?= $citas['pagina'] ?> de <?= $citas['paginas'] ?></span>
    <?php if ($citas['pagina'] < $citas['paginas']): ?><a class="text-link" href="?citas_pagina=<?= $citas['pagina'] + 1 ?>">Siguiente →</a><?php endif; ?></nav>
<?php endif; ?></section>
