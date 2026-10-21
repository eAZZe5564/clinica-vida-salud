<section class="page-heading page-heading-row"><div><p class="eyebrow">Pacientes · CU05</p><h1>Pacientes</h1><p class="lead">Consulta y administra los datos de recepción.</p></div><a class="button" href="/pacientes/nuevo">Registrar paciente</a></section>
<section class="panel table-panel">
    <form class="search-form" method="get" action="/pacientes">
        <label class="sr-only" for="patient-search">Buscar por CI, nombres o apellidos</label>
        <input id="patient-search" name="q" type="search" maxlength="100" value="<?= e($busqueda) ?>" placeholder="CI, nombres o apellidos">
        <button class="button button-secondary" type="submit">Buscar</button><a class="text-link" href="/pacientes">Limpiar</a>
    </form>
    <div class="table-scroll"><table><thead><tr><th>Código</th><th>CI</th><th>Paciente</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($resultado['filas'] as $fila): ?>
        <tr><td data-label="Código"><?= e(\App\Models\Paciente::codigo((int) $fila['id'])) ?></td><td data-label="CI"><?= e($fila['documento_identidad']) ?></td>
        <td data-label="Paciente"><?= e($fila['apellidos'] . ', ' . $fila['nombres']) ?></td>
        <td data-label="Estado"><span class="status-pill <?= $fila['activo'] ? 'status-active' : 'status-inactive' ?>"><?= $fila['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
        <td data-label="Acciones" class="row-actions"><a class="text-link" href="/pacientes/<?= (int) $fila['id'] ?>">Ver detalle</a><a class="text-link" href="/pacientes/<?= (int) $fila['id'] ?>/editar">Editar</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$resultado['filas']): ?><tr><td colspan="5" class="empty-state">No se encontraron pacientes.</td></tr><?php endif; ?>
    </tbody></table></div>
    <nav class="pagination" aria-label="Páginas de pacientes">
        <?php if ($resultado['pagina'] > 1): ?><a class="text-link" href="?<?= e(http_build_query(['q' => $busqueda, 'pagina' => $resultado['pagina'] - 1])) ?>">← Anterior</a><?php endif; ?>
        <span>Página <?= $resultado['pagina'] ?> de <?= $resultado['paginas'] ?> · <?= $resultado['total'] ?> pacientes</span>
        <?php if ($resultado['pagina'] < $resultado['paginas']): ?><a class="text-link" href="?<?= e(http_build_query(['q' => $busqueda, 'pagina' => $resultado['pagina'] + 1])) ?>">Siguiente →</a><?php endif; ?>
    </nav>
</section>
