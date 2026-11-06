<?php $cancelar=$accionPortal==='cancelar';$nueva=$accionPortal==='nueva';$ruta=$nueva?'/mis-citas':'/mis-citas/'.(int)$cita['id'].'/'.$accionPortal; ?>
<section class="page-heading portal"><h1><?= $nueva?'Reservar cita':($cancelar?'Cancelar cita':'Reprogramar cita') ?></h1><p>Horarios de America/La_Paz · turnos de 30 minutos.</p><a href="/mis-citas">Volver a mis citas</a></section>
<?php if($cita): ?><section class="panel portal"><h2><?= e($cita['codigo']) ?></h2><p><?= e($cita['inicio_local'].' · '.$cita['medico_nombres'].' '.$cita['medico_apellidos'].' · '.($cita['especialidad']??'Sin registrar')) ?></p></section><?php endif; ?>
<?php if(!$cancelar): ?>
<section class="panel portal"><h2>Consultar turnos libres</h2><form method="get" class="portal-grid" data-portal-disponibilidad>
<?php if($nueva): ?><label>Especialidad<select name="especialidad_id" required><option value="">Selecciona una especialidad</option><?php foreach($especialidades as $e): ?><option value="<?= (int)$e['id'] ?>" <?= $seleccion['especialidad_id']===(string)$e['id']?'selected':'' ?>><?= e($e['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Médico<select name="medico_id"><option value="">Selecciona tras consultar la especialidad</option><?php foreach($medicos as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $seleccion['medico_id']===(string)$m['id']?'selected':'' ?>><?= e($m['nombres'].' '.$m['apellidos']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label>Fecha<input type="date" name="fecha" value="<?= e($seleccion['fecha']) ?>" required></label><button type="submit" class="button">Consultar disponibilidad</button></form></section>
<?php endif; ?>
<section class="panel portal"><h2><?= $cancelar?'Confirmar cancelación':'Seleccionar y confirmar' ?></h2>
<?php if(!$cancelar&&!$turnos): ?><p>No hay turnos para esta selección. Consulta una especialidad, médico y fecha válidos.</p>
<?php else: ?><form method="post" action="<?= e($ruta) ?>" data-portal-confirmacion>
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<?php if(!$nueva): ?><input type="hidden" name="original" value="<?= e($original) ?>"><?php endif; ?>
<?php if(!$cancelar): ?>
<?php foreach(['especialidad_id','medico_id','fecha'] as $k): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($seleccion[$k]) ?>"><?php endforeach; ?>
<label>Turno libre<select name="hora_inicio" required><option value="">Selecciona un horario</option><?php foreach($turnos as $t): $i=(new DateTimeImmutable($t['inicio']))->setTimezone(\App\Models\HorarioAtencion::zona());$fin=(new DateTimeImmutable($t['fin']))->setTimezone(\App\Models\HorarioAtencion::zona()); ?><option value="<?= e($i->format('H:i')) ?>"><?= e($i->format('H:i').'–'.$fin->format('H:i')) ?></option><?php endforeach; ?></select></label>
<?php else: ?><p>La cita quedará cancelada. Su historial se conservará.</p><label>Motivo de cancelación (opcional)<textarea name="motivo_cancelacion" maxlength="250" rows="3"></textarea></label><?php endif; ?>
<label class="portal-check"><input type="checkbox" name="confirmar" value="1" required> <?= $cancelar?'Confirmo que deseo cancelar mi cita.':($nueva?'Confirmo la reserva para mi cuenta vinculada.':'Confirmo el nuevo horario para mi cita.') ?></label>
<button class="button" type="submit"><?= $cancelar?'Confirmar cancelación':($nueva?'Confirmar reserva':'Confirmar reprogramación') ?></button>
</form><?php endif; ?></section>
