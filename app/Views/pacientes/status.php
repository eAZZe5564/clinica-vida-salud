<section class="page-heading"><p class="eyebrow">Pacientes · CU06</p><h1><?= $paciente['activo'] ? 'Confirmar desactivación' : 'Confirmar reactivación' ?></h1></section>
<form class="panel user-form" method="post" action="/pacientes/<?= (int) $paciente['id'] ?>/estado">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="activo" value="<?= $paciente['activo'] ? '0' : '1' ?>">
    <p>Vas a <?= $paciente['activo'] ? 'desactivar' : 'reactivar' ?> a <strong><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></strong> (<?= e(\App\Models\Paciente::codigo((int) $paciente['id'])) ?>).</p>
    <p>Sus datos y citas se conservarán. <?= $paciente['activo'] ? 'Un paciente inactivo no puede recibir nuevas reservas.' : 'El paciente volverá a estar activo.' ?></p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Confirmar <?= $paciente['activo'] ? 'desactivación' : 'reactivación' ?></button><a class="button button-quiet" href="/pacientes/<?= (int) $paciente['id'] ?>">Cancelar</a></div>
</form>
