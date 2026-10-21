<section class="page-heading"><p class="eyebrow"><?= e(\App\Models\Medico::codigo((int) $medico['id'])) ?> · CU08</p><h1><?= e($medico['nombres'] . ' ' . $medico['apellidos']) ?></h1></section>
<section class="panel patient-detail">
    <dl class="detail-grid"><?php foreach (['numero_colegiatura' => 'Registro profesional','telefono' => 'Teléfono','correo_contacto' => 'Correo electrónico'] as $campo => $etiqueta): ?><div><dt><?= e($etiqueta) ?></dt><dd><?= e($medico[$campo] ?? 'Sin registrar') ?></dd></div><?php endforeach; ?>
    <div><dt>Estado</dt><dd><?= $medico['activo'] ? 'Activo' : 'Inactivo' ?></dd></div></dl>
    <h2 class="catalog-subtitle">Especialidades</h2><ul><?php foreach ($asignadas as $especialidad): ?><li><?= e($especialidad['nombre']) ?><?= $especialidad['activo'] ? '' : ' (inactiva)' ?></li><?php endforeach; ?></ul>
    <?php if (!$asignadas): ?><p>Sin especialidades asignadas.</p><?php endif; ?>
    <p class="form-hint">Los horarios semanales se muestran abajo. La disponibilidad y reserva de citas se implementarán posteriormente.</p>
    <div class="form-actions"><?php if ($esAdmin): ?><a class="button" href="/medicos/<?= (int) $medico['id'] ?>/editar">Editar médico</a><a class="button button-quiet" href="/medicos/<?= (int) $medico['id'] ?>/especialidades">Asignar especialidades</a><a class="text-link" href="/medicos/<?= (int) $medico['id'] ?>/estado"><?= $medico['activo'] ? 'Desactivar' : 'Reactivar' ?></a><?php endif; ?><a class="text-link" href="/medicos">Volver al listado</a></div>
</section>
<section class="panel table-panel patient-appointments"><h2 class="catalog-subtitle">Horarios semanales registrados</h2><p class="form-hint"><?= e(\App\Models\HorarioAtencion::zona()->getName()) ?> · duración de citas: 30 minutos.</p>
    <?php $resultadoHorarios=$horarios; require dirname(__DIR__) . '/horarios/tabla.php'; ?>
    <nav class="pagination" aria-label="Páginas de horarios"><?php if ($horarios['pagina']>1): ?><a class="text-link" href="?horarios_pagina=<?= $horarios['pagina']-1 ?>">← Anterior</a><?php endif; ?><span>Página <?= $horarios['pagina'] ?> de <?= $horarios['paginas'] ?></span><?php if ($horarios['pagina']<$horarios['paginas']): ?><a class="text-link" href="?horarios_pagina=<?= $horarios['pagina']+1 ?>">Siguiente →</a><?php endif; ?></nav>
    <div class="form-actions"><a class="text-link" href="/horarios?medico=<?= (int)$medico['id'] ?>">Consultar horarios de este médico</a><?php if ($esAdmin): ?><a class="button" href="/horarios/nuevo?medico=<?= (int)$medico['id'] ?>">Registrar horario</a><?php endif; ?></div>
</section>
