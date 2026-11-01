<?php
$marcas=[];
foreach($historialAsistencia as $evento){if(in_array($evento['tipo'],['llegada','atencion','inasistencia'],true)){$marcas[$evento['tipo']]=$evento;}}
$fechaEvento=static fn(string $valor):string=>(new \DateTimeImmutable($valor))->setTimezone(\App\Models\HorarioAtencion::zona())->format('d/m/Y H:i:s');
?>
<section class="panel asistencia-panel"><h2>Estado administrativo de asistencia</h2><dl class="detail-grid">
<?php foreach(['llegada'=>'Llegada registrada','atencion'=>'Atención completada','inasistencia'=>'Inasistencia registrada'] as $tipo=>$label): ?>
<div><dt><?= e($label) ?></dt><dd><?= isset($marcas[$tipo])?e($fechaEvento($marcas[$tipo]['cambiado_en'])):'Sin registro' ?></dd></div>
<?php endforeach; ?></dl><p class="field-hint">Fechas de America/La_Paz. La llegada no equivale a atención terminada; no se infieren marcas para citas históricas sin evento.</p>
<?php if($usuario['rol']==='administrador'): ?>
<h3>Trazabilidad administrativa</h3>
<?php if(!$historialAsistencia): ?><p>Sin eventos registrados.</p><?php else: ?>
<div class="table-scroll"><table class="asistencia-historial"><thead><tr><th>Evento</th><th>Estado anterior</th><th>Estado nuevo</th><th>Responsable</th><th>Momento</th></tr></thead><tbody>
<?php foreach($historialAsistencia as $evento): ?><tr><td data-label="Evento"><?= e(['reprogramacion'=>'Reprogramación','cancelacion'=>'Cancelación','confirmacion'=>'Confirmación','llegada'=>'Llegada','atencion'=>'Atención completada','inasistencia'=>'Inasistencia'][$evento['tipo']]) ?></td><td data-label="Estado anterior"><?= e(\App\Models\Cita::ESTADOS[$evento['estado_anterior']]) ?></td><td data-label="Estado nuevo"><?= e(\App\Models\Cita::ESTADOS[$evento['estado_nuevo']]) ?></td><td data-label="Responsable"><?= e($evento['responsable']) ?></td><td data-label="Momento"><?= e($fechaEvento($evento['cambiado_en'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php endif; ?></section>
