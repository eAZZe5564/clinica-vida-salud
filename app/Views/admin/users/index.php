<section class="page-heading page-heading-row">
    <div>
        <p class="eyebrow">Administración · CU03</p>
        <h1>Usuarios y roles</h1>
        <p class="lead">Gestiona las cuentas de acceso sin eliminar el historial.</p>
    </div>
    <a class="button" href="/admin/users/new">Crear usuario</a>
</section>

<section class="panel table-panel">
    <form class="search-form" method="get" action="/admin/users">
        <label class="sr-only" for="user-search">Buscar por correo o rol</label>
        <input id="user-search" name="q" type="search" maxlength="100" value="<?= e($busqueda) ?>" placeholder="Buscar por correo o rol">
        <button class="button button-secondary" type="submit">Buscar</button>
        <?php if ($busqueda !== ''): ?><a class="text-link" href="/admin/users">Limpiar</a><?php endif; ?>
    </form>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>Correo</th><th>Rol</th><th>Estado</th><th>Creada</th><th class="actions-heading">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($cuentas as $cuenta): ?>
                    <tr>
                        <td data-label="Correo"><?= e($cuenta['correo']) ?></td>
                        <td data-label="Rol"><?= e($nombresRoles[$cuenta['rol_nombre']] ?? $cuenta['rol_nombre']) ?></td>
                        <td data-label="Estado"><span class="status-pill <?= $cuenta['activo'] ? 'status-active' : 'status-inactive' ?>"><?= $cuenta['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
                        <td data-label="Creada"><?= e(date('Y-m-d', strtotime($cuenta['creado_en']))) ?></td>
                        <td data-label="Acciones" class="row-actions">
                            <a class="text-link" href="/admin/users/<?= (int) $cuenta['id'] ?>/edit">Editar</a>
                            <form method="post" action="/admin/users/<?= (int) $cuenta['id'] ?>/status">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="activo" value="<?= $cuenta['activo'] ? '0' : '1' ?>">
                                <button class="link-button <?= $cuenta['activo'] ? 'danger-link' : '' ?>" type="submit"><?= $cuenta['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$cuentas): ?>
                    <tr><td colspan="5" class="empty-state">No se encontraron cuentas<?= $busqueda !== '' ? ' para esta búsqueda' : '' ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="table-footnote">Se muestran hasta 100 resultados. Las cuentas se desactivan; no se borran físicamente.</p>
</section>
