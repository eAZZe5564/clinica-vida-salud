<section class="page-heading page-heading-row"><div><p class="eyebrow">Atención médica · CU11</p><h1>Horarios médicos</h1><p class="lead">Horarios semanales · <?= e(\App\Models\HorarioAtencion::zona()->getName()) ?> · citas de 30 minutos.</p></div><?php if ($esAdmin): ?><a class="button" href="/horarios/nuevo">Registrar horario</a><?php endif; ?></section>
<section class="panel table-panel">
<?php if ($sinVinculo): ?><p class="notice notice-error">Tu cuenta no tiene un registro médico vinculado. Solicita al administrador que revise la asociación; no se muestran horarios de otros médicos.</p>
<?php else: ?>
    <form class="search-form catalog-search" method="get" action="/horarios">
        <div class="field-group"><label for="medico">Médico</label><select id="medico" name="medico"><?php if ($usuario['rol']!=='medico'): ?><option value="">Todos los médicos</option><?php endif; ?><?php foreach ($opciones as $opcion): ?><option value="<?= (int)$opcion['id'] ?>" <?= $medico===(int)$opcion['id']?'selected':'' ?>><?= e($opcion['apellidos'] . ', ' . $opcion['nombres']) ?><?= $opcion['activo']?'':' (inactivo)' ?></option><?php endforeach; ?></select></div>
        <div class="field-group"><label for="dia">Día de la semana</label><select id="dia" name="dia"><option value="">Todos los días</option><?php foreach (\App\Models\HorarioAtencion::DIAS as $numero=>$nombre): ?><option value="<?= $numero ?>" <?= $dia===$numero?'selected':'' ?>><?= e($nombre) ?></option><?php endforeach; ?></select></div>
        <button class="button button-secondary" type="submit">Filtrar</button><a class="text-link" href="/horarios">Limpiar</a>
    </form>
    <?php $resultadoHorarios=$resultado; require __DIR__ . '/tabla.php'; ?>
    <?php $filtros=['medico'=>$medico,'dia'=>$dia]; require dirname(__DIR__) . '/catalogo/paginacion.php'; ?>
<?php endif; ?>
</section>
