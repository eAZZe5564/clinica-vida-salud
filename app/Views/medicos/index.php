<section class="page-heading page-heading-row"><div><p class="eyebrow">Profesionales · CU08</p><h1>Médicos</h1><p class="lead">Busca profesionales y consulta sus especialidades.</p></div><?php if ($esAdmin): ?><a class="button" href="/medicos/nuevo">Registrar médico</a><?php endif; ?></section>
<section class="panel table-panel">
    <form class="search-form catalog-search" method="get" action="/medicos">
        <div class="field-group"><label for="medico-q">Nombre, apellido o registro</label><input id="medico-q" name="q" type="search" maxlength="100" value="<?= e($busqueda) ?>"></div>
        <div class="field-group"><label for="especialidad">Especialidad</label><select id="especialidad" name="especialidad"><option value="">Todas</option><?php foreach ($opciones as $opcion): ?><option value="<?= (int) $opcion['id'] ?>" <?= $filtro === (int) $opcion['id'] ? 'selected' : '' ?>><?= e($opcion['nombre']) ?><?= $opcion['activo'] ? '' : ' (inactiva)' ?></option><?php endforeach; ?></select></div>
        <div class="field-group"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
        <button class="button button-secondary" type="submit">Buscar</button><a class="text-link" href="/medicos">Limpiar</a>
    </form>
    <div class="table-scroll"><table><thead><tr><th>Código / Registro</th><th>Médico</th><th>Especialidades</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($resultado['filas'] as $fila): ?><tr>
        <td data-label="Código / Registro"><span><?= e(\App\Models\Medico::codigo((int) $fila['id'])) ?><br><?= e($fila['numero_colegiatura']) ?></span></td>
        <td data-label="Médico"><?= e($fila['apellidos'] . ', ' . $fila['nombres']) ?></td><td data-label="Especialidades"><?= e($fila['especialidades'] ?: 'Sin especialidades') ?></td>
        <td data-label="Estado"><span class="status-pill <?= $fila['activo'] ? 'status-active' : 'status-inactive' ?>"><?= $fila['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
        <td data-label="Acciones"><a class="text-link" href="/medicos/<?= (int) $fila['id'] ?>">Ver detalle</a></td></tr><?php endforeach; ?>
        <?php if (!$resultado['filas']): ?><tr><td colspan="5" class="empty-state">No se encontraron médicos.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php $filtros = ['q' => $busqueda, 'especialidad' => $filtro, 'estado' => $estado]; require dirname(__DIR__) . '/catalogo/paginacion.php'; ?>
</section>
