<section class="page-heading"><p class="eyebrow">Horarios · CU11</p><h1>Confirmar <?= $horario['activo']?'desactivación':'reactivación' ?></h1></section>
<form class="panel user-form" method="post" action="/horarios/<?= (int)$horario['id'] ?>/estado">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="activo" value="<?= $horario['activo']?'0':'1' ?>">
    <?php foreach ($errores as $error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endforeach; ?>
    <p><?= e($horario['nombres'] . ' ' . $horario['apellidos']) ?> · <?= e(\App\Models\HorarioAtencion::DIAS[(int)$horario['dia_semana']]) ?> · <?= e(substr($horario['hora_inicio'],0,5) . '–' . substr($horario['hora_fin'],0,5)) ?></p>
    <p>Se conservará el registro. La operación se rechazará si provoca conflictos o deja una cita futura programada o confirmada sin cobertura; no se cancelarán ni reprogramarán citas.</p>
    <div class="form-actions"><button class="button button-secondary" type="submit">Confirmar <?= $horario['activo']?'desactivación':'reactivación' ?></button><a class="button button-quiet" href="/horarios/<?= (int)$horario['id'] ?>">Cancelar</a></div>
</form>
