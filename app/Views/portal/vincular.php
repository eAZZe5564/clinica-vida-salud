<section class="panel portal"><h1>Vinculación inicial de cuenta Paciente</h1><h2><?= e($paciente['nombres'].' '.$paciente['apellidos']) ?></h2>
<?php if($recibo): ?><p class="notice notice-exito" role="status">Vinculación realizada por el administrador #<?= (int)$recibo['administrador_id'] ?> el <?= e((new DateTimeImmutable($recibo['realizada_en']))->setTimezone(\App\Models\HorarioAtencion::zona())->format('d/m/Y H:i:s')) ?>.</p><?php endif; ?>
<p>Verifica la identidad por el procedimiento confiable de la clínica antes de seleccionar la cuenta. Coincidir en CI, nombre, correo o teléfono no demuestra identidad.</p>
<p>Solo se permite el vínculo inicial. No se puede sustituir, desvincular ni reasignar.</p>
<?php if(!$paciente['activo']||$paciente['usuario_id']!==null): ?><p class="notice">El paciente está inactivo o ya tiene una cuenta vinculada. No se permite vincular.</p>
<?php elseif(!$cuentas): ?><p>No hay cuentas Paciente activas disponibles.</p>
<?php else: ?><form method="post" action="/pacientes/<?= (int)$paciente['id'] ?>/vincular-cuenta" data-portal-vincular>
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Cuenta Paciente activa<select name="cuenta_id" required><option value="">Selecciona tras verificar su identidad</option><?php foreach($cuentas as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['correo']) ?></option><?php endforeach; ?></select></label>
<p class="form-hint">Se muestran hasta 100 cuentas disponibles, ordenadas por correo.</p>
<label class="portal-check"><input type="checkbox" name="identidad_verificada" value="1" required> Confirmo que verifiqué por un procedimiento confiable que esta cuenta pertenece a este paciente y autorizo la vinculación inicial.</label>
<button class="button" type="submit">Confirmar vinculación inicial</button></form><?php endif; ?>
<p class="form-hint">Limitación de auditoría: el recibo de esta operación se muestra una vez en la sesión. No existe una bitácora persistente que conserve al administrador responsable y la fecha del vínculo. La fecha de actualización del paciente no sustituye esa auditoría.</p>
<a href="/pacientes/<?= (int)$paciente['id'] ?>">Volver al paciente</a></section>
