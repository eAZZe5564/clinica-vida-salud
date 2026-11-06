<section class="panel portal"><h1>Operación no disponible</h1>
<div class="notice notice-error" role="alert"><ul><?php foreach($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<p>No se completó la operación. Vuelve a consultar antes de confirmarla.</p>
<a class="button" href="<?= !empty($administrativa)?'/pacientes':'/mis-citas' ?>">Volver</a></section>
