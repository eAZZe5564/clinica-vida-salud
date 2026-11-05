<?php use App\Models\Cita; $r=$datos['reporte'];$g=$r['general']; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Reporte de citas médicas</title><style>
@page { margin: 28pt 30pt 38pt; }
body { font-family: DejaVu Sans; font-size: 8pt; color: #193b3b; }
h1 { font-size: 19pt; margin: 0 0 7pt; } h2 { font-size: 11pt; margin: 16pt 0 5pt; page-break-after: avoid; }
p { line-height: 1.4; } table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8pt; }
thead { display: table-header-group; } tr { page-break-inside: avoid; }
th,td { padding: 5pt; border-bottom: .5pt solid #d6e3e0; vertical-align: top; word-wrap: break-word; }
th { background: #edf5f2; text-align: left; font-size: 7pt; } td.numero { text-align: right; }
.grupo { width: 27%; } .meta th { width: 22%; } .nota { color: #476362; font-size: 7.5pt; }
</style></head><body>
<h1>Clínica Vida Salud</h1><h2>Reporte de citas médicas</h2><p>Generado: <?= $e($generado) ?> · America/La_Paz.</p>
<table class="meta"><tbody><?php foreach($documento->filtros() as $campo=>$valor): ?><tr><th><?= $e($campo) ?></th><td><?= $e($valor) ?></td></tr><?php endforeach; ?></tbody></table>
<p class="nota">Estado actual de las citas cuyo inicio programado vigente está dentro del período. Cada cita se cuenta una vez, incluso si fue reprogramada. No se cuentan eventos del historial ni fechas de creación o cancelación.</p>
<h2>Resumen general y situación de asistencia</h2>
<table><thead><tr><th>Total</th><?php foreach(Cita::ESTADOS as $label): ?><th><?= $e($label) ?></th><?php endforeach; ?></tr></thead><tbody><tr><td class="numero"><?= $g['total'] ?></td><?php foreach(Cita::ESTADOS as $estado=>$label): ?><td class="numero"><?= $g[$estado] ?></td><?php endforeach; ?></tr></tbody></table>
<p>Pendientes de llegada: programadas <?= $g['programada'] ?> y confirmadas <?= $g['confirmada'] ?>. Presentes con atención sin finalizar: <?= $g['presente'] ?>. Atendidas: <?= $g['atendida'] ?>; inasistencias: <?= $g['inasistencia'] ?>; canceladas: <?= $g['cancelada'] ?>.</p>
<p class="nota">Los seis estados son excluyentes y suman el total. Puede haber citas futuras: no son tasas clínicas ni estadísticas históricas de transiciones.</p>
<?php if($g['total']===0): ?><p>No hay citas para el período y los filtros seleccionados.</p><?php endif; ?>
<h2>Distribución por estados</h2><table><thead><tr><th>Estado</th><th>Cantidad</th></tr></thead><tbody><?php foreach(Cita::ESTADOS as $estado=>$label): ?><tr><td><?= $e($label) ?></td><td class="numero"><?= $r['estados'][$estado] ?></td></tr><?php endforeach; ?></tbody></table>
<?php foreach(['medicos'=>'Citas por médico','especialidades'=>'Citas por especialidad','dias'=>'Resumen diario'] as $grupo=>$titulo): ?>
<h2><?= $e($titulo) ?></h2>
<?php if($grupo==='especialidades'): ?><p class="nota">Especialidad guardada en la cita; valores históricos nulos: Sin registrar.</p><?php endif; ?>
<?php if($grupo==='dias'): ?><p class="nota">Días de inicio en America/La_Paz. Los días omitidos tienen cero citas.</p><?php endif; ?>
<?php if(!$r[$grupo]): ?><p>Sin registros.</p><?php else: ?>
<table><thead><tr><th class="grupo"><?= $grupo==='dias'?'Fecha':'Grupo' ?></th><th>Total</th><?php foreach(Cita::ESTADOS as $label): ?><th><?= $e($label) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($r[$grupo] as $fila): ?><tr><td><?= $e($fila['etiqueta']) ?></td><td class="numero"><?= $fila['total'] ?></td><?php foreach(Cita::ESTADOS as $estado=>$label): ?><td class="numero"><?= $fila[$estado] ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<tr><td>Total</td><td class="numero"><?= $g['total'] ?></td><?php foreach(Cita::ESTADOS as $estado=>$label): ?><td class="numero"><?= $g[$estado] ?></td><?php endforeach; ?></tr>
</tbody></table><?php endif; ?><?php endforeach; ?>
</body></html>
