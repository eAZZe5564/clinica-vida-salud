<div class="table-scroll"><table><thead><tr><th>Médico / Especialidades</th><th>Día</th><th>Horario</th><th>Duración</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($resultadoHorarios['filas'] as $filaHorario): ?><tr>
    <td data-label="Médico / Especialidades"><span><?= e($filaHorario['apellidos'] . ', ' . $filaHorario['nombres']) ?><?= $filaHorario['medico_activo'] ? '' : ' (médico inactivo)' ?><br><small><?= e($filaHorario['especialidades']) ?></small></span></td>
    <td data-label="Día"><?= e(\App\Models\HorarioAtencion::DIAS[(int)$filaHorario['dia_semana']]) ?></td>
    <td data-label="Horario"><?= e(substr($filaHorario['hora_inicio'],0,5) . '–' . substr($filaHorario['hora_fin'],0,5)) ?></td>
    <td data-label="Duración"><?= \App\Models\HorarioAtencion::DURACION_MINUTOS ?> min</td>
    <td data-label="Estado"><span class="status-pill <?= $filaHorario['activo']?'status-active':'status-inactive' ?>"><?= $filaHorario['activo']?'Activo':'Inactivo' ?></span></td>
    <td data-label="Acciones"><a class="text-link" href="/horarios/<?= (int)$filaHorario['id'] ?>">Ver detalle</a><?php if ($esAdmin): ?> · <a class="text-link" href="/horarios/<?= (int)$filaHorario['id'] ?>/editar">Editar</a><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$resultadoHorarios['filas']): ?><tr><td colspan="6" class="empty-state">No se encontraron horarios registrados.</td></tr><?php endif; ?>
</tbody></table></div>
