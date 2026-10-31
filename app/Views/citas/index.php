<?php
$consultaQuery=array_filter($filtros,static fn($v)=>$v!=='');
$consultaQuery['pagina']=$resultado['pagina'];
?>
<section class="page-heading page-heading-row"><div><p class="eyebrow">Consulta de reservas · CU14</p><h1>Citas médicas</h1><p class="lead"><?= $usuario['rol']==='medico'?'Consulta las citas de tu agenda.':'Busca las citas registradas y consulta sus detalles.' ?></p></div>
<?php if($usuario['rol']!=='medico'): ?><a class="button" href="/citas/nueva">Programar cita</a><?php endif; ?></section>
<?php if($sinVinculo): ?><section class="panel"><h2>Cuenta sin médico vinculado</h2><p>Tu cuenta todavía no está vinculada a un registro médico. Solicita al administrador que revise la vinculación para consultar tu agenda.</p></section>
<?php else: ?>
<section class="panel appointment-filters" aria-labelledby="filtros-citas"><h2 id="filtros-citas">Buscar citas</h2>
<?php if($errores): ?><div class="notice notice-error" role="alert"><p>Revisa los filtros antes de consultar.</p><ul><?php foreach($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="get" action="/citas" class="appointment-filter-grid">
    <div class="field-group"><label for="cita-codigo">Código de cita</label><input id="cita-codigo" name="codigo" maxlength="22" placeholder="CM-000001" value="<?= e($filtros['codigo']) ?>"></div>
    <div class="field-group"><label for="cita-periodo">Período</label><select id="cita-periodo" name="periodo"><?php foreach(['todas'=>'Todas las fechas','proximas'=>'Próximas citas','historicas'=>'Citas pasadas'] as $valor=>$etiqueta): ?><option value="<?= e($valor) ?>" <?= ($filtros['periodo']?:'todas')===$valor?'selected':'' ?>><?= e($etiqueta) ?></option><?php endforeach; ?></select></div>
    <div class="field-group"><label for="cita-fecha">Fecha exacta</label><input id="cita-fecha" type="date" name="fecha" value="<?= e($filtros['fecha']) ?>"></div>
    <div class="field-group"><label for="cita-desde">Desde</label><input id="cita-desde" type="date" name="desde" value="<?= e($filtros['desde']) ?>"></div>
    <div class="field-group"><label for="cita-hasta">Hasta (incluida)</label><input id="cita-hasta" type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
    <div class="field-group"><label for="cita-medico">Médico</label><select id="cita-medico" name="medico_id"><option value=""><?= $usuario['rol']==='medico'?'Mi agenda':'Todos los médicos' ?></option><?php foreach($medicos as $medico): ?><option value="<?= (int)$medico['id'] ?>" <?= (string)$medico['id']===$filtros['medico_id']?'selected':'' ?>><?= e($medico['apellidos'] . ', ' . $medico['nombres'] . ($medico['activo']?'':' (inactivo)')) ?></option><?php endforeach; ?></select></div>
    <div class="field-group"><label for="cita-especialidad">Especialidad</label><select id="cita-especialidad" name="especialidad_id"><option value="">Todas las especialidades</option><?php foreach($especialidades as $especialidad): ?><option value="<?= (int)$especialidad['id'] ?>" <?= (string)$especialidad['id']===$filtros['especialidad_id']?'selected':'' ?>><?= e($especialidad['nombre'] . ($especialidad['activo']?'':' (inactiva)')) ?></option><?php endforeach; ?></select></div>
    <div class="field-group"><label for="cita-estado">Estado</label><select id="cita-estado" name="estado"><option value="">Todos los estados</option><?php foreach(\App\Models\Cita::ESTADOS as $valor=>$etiqueta): ?><option value="<?= e($valor) ?>" <?= $filtros['estado']===$valor?'selected':'' ?>><?= e($etiqueta) ?></option><?php endforeach; ?></select></div>
    <div class="form-actions appointment-filter-actions"><button class="button" type="submit">Buscar citas</button><a class="text-link" href="/citas">Limpiar filtros</a></div>
</form>
<p class="field-hint">Horas de America/La_Paz. Todas y próximas: de la más antigua a la más reciente. Pasadas: de la más reciente a la más antigua. La fecha exacta y el rango se combinan.</p></section>
<section class="panel table-panel" aria-label="Resultados de citas">
<div class="table-scroll"><table class="appointments-table"><thead><tr><th>Código</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Fecha</th><th>Inicio</th><th>Final</th><th>Estado</th><th>Consulta</th></tr></thead><tbody>
<?php foreach($resultado['filas'] as $fila): ?>
<tr data-cita-id="<?= (int)$fila['id'] ?>"><td data-label="Código"><?= e($fila['codigo']) ?></td><td data-label="Paciente"><?= e($fila['paciente_apellidos'] . ', ' . $fila['paciente_nombres']) ?></td><td data-label="Médico"><?= e($fila['medico_apellidos'] . ', ' . $fila['medico_nombres']) ?></td><td data-label="Especialidad"><?= e($fila['especialidad']??'Sin registrar') ?></td><td data-label="Fecha"><?= e($fila['fecha']) ?></td><td data-label="Inicio"><?= e($fila['hora_inicio']) ?></td><td data-label="Final"><?= e($fila['hora_fin']) ?></td><td data-label="Estado"><span class="status-pill appointment-<?= e($fila['estado']) ?>"><?= e(\App\Models\Cita::ESTADOS[$fila['estado']]) ?></span></td><td data-label="Consulta"><a class="text-link" href="/citas/<?= (int)$fila['id'] ?>?<?= e(http_build_query($consultaQuery)) ?>">Ver detalle</a><?php if(in_array($usuario['rol'],['administrador','recepcionista'],true)&&$fila['reprogramable']): ?> · <a class="text-link" href="/citas/<?= (int)$fila['id'] ?>/reprogramar">Reprogramar</a><?php endif; ?><?php if(in_array($usuario['rol'],['administrador','recepcionista'],true)&&$fila['estado']==='programada'): ?> · <a class="text-link" href="/citas/<?= (int)$fila['id'] ?>/cancelar">Cancelar cita</a><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if(!$resultado['filas']): ?><tr><td colspan="9" class="empty-state"><?= $errores?'Corrige los filtros para consultar las citas.':'No se encontraron citas con los filtros seleccionados.' ?></td></tr><?php endif; ?>
</tbody></table></div>
<?php if(!$errores): ?><nav class="pagination" aria-label="Páginas de citas">
<?php if($resultado['pagina']>1): ?><a class="text-link" href="/citas?<?= e(http_build_query(array_replace($consultaQuery,['pagina'=>$resultado['pagina']-1]))) ?>">← Anterior</a><?php endif; ?>
<span>Página <?= $resultado['pagina'] ?> de <?= $resultado['paginas'] ?> · <?= $resultado['total'] ?> citas</span>
<?php if($resultado['pagina']<$resultado['paginas']): ?><a class="text-link" href="/citas?<?= e(http_build_query(array_replace($consultaQuery,['pagina'=>$resultado['pagina']+1]))) ?>">Siguiente →</a><?php endif; ?></nav><?php endif; ?>
</section><?php endif; ?>
