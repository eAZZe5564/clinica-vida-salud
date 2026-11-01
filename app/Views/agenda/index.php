<?php
use App\Models\Cita;
use App\Services\AgendaService;
$url=static fn(string $v,string $fecha):string=>AgendaService::url($v,$fecha,$medicoId,$f['estado']);
$hora=static fn(\DateTimeImmutable $d):string=>$d->format('H:i');
?>
<section class="page-heading"><p class="eyebrow">Consulta de atención · CU19</p><h1>Agenda médica</h1><p class="lead">Vista <?= $modoAgenda==='diaria'?'diaria':'semanal, de lunes a domingo' ?> · America/La_Paz</p></section>
<?php if($sinVinculo): ?>
<p class="notice notice-error" role="status">Tu cuenta no tiene un registro médico vinculado. No se muestran agendas de otros profesionales.</p>
<?php else: ?>
<form method="get" action="/agenda/<?= e($modoAgenda) ?>" class="panel agenda-panel agenda-filtros" data-agenda-filtros>
<?php if($medicoId===null||$usuario['rol']!=='medico'): ?>
<div class="field-group"><label for="agenda-medico">Médico</label><select id="agenda-medico" name="medico_id"><option value="">Selecciona un profesional</option><?php foreach($medicos as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $medicoId===(int)$m['id']?'selected':'' ?>><?= e($m['apellidos'].', '.$m['nombres'].($m['activo']?'':' (inactivo)')) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
<div class="field-group"><label for="agenda-fecha">Fecha de referencia</label><input id="agenda-fecha" type="date" name="fecha" value="<?= e($f['fecha']) ?>" required></div>
<div class="field-group"><label for="agenda-estado">Estado</label><select id="agenda-estado" name="estado"><?php foreach(AgendaService::FILTROS as $valor=>$label): ?><option value="<?= e($valor) ?>" <?= $f['estado']===$valor?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<button class="button" type="submit">Consultar agenda</button></form>
<nav class="agenda-navegacion" aria-label="Vista y fecha de agenda">
<div class="agenda-vistas"><a class="button button-secondary" data-agenda-vista="diaria" <?= $modoAgenda==='diaria'?'aria-current="page"':'' ?> href="<?= e($url('diaria',$f['fecha'])) ?>">Diaria</a><a class="button button-secondary" data-agenda-vista="semanal" <?= $modoAgenda==='semanal'?'aria-current="page"':'' ?> href="<?= e($url('semanal',$f['fecha'])) ?>">Semanal</a></div>
<div class="agenda-fechas"><?php if($navegacion['anterior']): ?><a class="text-link" data-agenda-anterior href="<?= e($navegacion['anterior']) ?>">← <?= $modoAgenda==='diaria'?'Día':'Semana' ?> anterior</a><?php endif; ?><a class="button button-secondary" data-agenda-hoy href="<?= e($navegacion['hoy']) ?>"><?= $modoAgenda==='diaria'?'Hoy':'Semana actual' ?></a><?php if($navegacion['siguiente']): ?><a class="text-link" data-agenda-siguiente href="<?= e($navegacion['siguiente']) ?>"><?= $modoAgenda==='diaria'?'Día':'Semana' ?> siguiente →</a><?php endif; ?></div>
</nav>
<?php if(!$medico): ?><p class="panel agenda-panel" role="status">Selecciona un médico para consultar su agenda. No se cargan agendas generales.</p><?php else: ?>
<section class="panel agenda-panel"><h2><?= e($medico['nombres'].' '.$medico['apellidos']) ?><?= $medico['activo']?'':' (inactivo)' ?></h2>
<p>Especialidades: <?php if(!$especialidades): ?>Sin especialidades asignadas<?php else: ?><?= e(implode(', ',array_map(static fn($e)=>$e['nombre'].($e['activo']?'':' (inactiva)'),$especialidades))) ?><?php endif; ?>.</p>
<p>Del <?= e($desde->format('d/m/Y')) ?> al <?= e($hasta->modify('-1 day')->format('d/m/Y')) ?>. Filtro: <?= e(AgendaService::FILTROS[$f['estado']]) ?>.</p>
<p class="field-hint">Consulta sin cambios. «Sin cita registrada» describe la agenda; consulta Disponibilidad para reservar. Las franjas son la configuración actual de atención; las citas históricas se conservan aunque queden fuera de ella.</p>
<div class="agenda-leyenda" aria-label="Leyenda de estados"><?php foreach(Cita::ESTADOS as $valor=>$label): ?><span class="status-pill appointment-<?= e($valor) ?>"><?= e($label) ?></span><?php endforeach; ?></div></section>
<div class="agenda-dias <?= $modoAgenda==='semanal'?'agenda-semanal':'agenda-diaria' ?>" data-agenda-vista-actual="<?= e($modoAgenda) ?>">
<?php foreach($dias as $dia): ?>
<section class="panel agenda-dia" data-agenda-dia="<?= e($dia['fecha']) ?>"><header><h2><?= e($dia['titulo']) ?></h2><time datetime="<?= e($dia['fecha']) ?>"><?= e($dia['rotulo']) ?></time><p class="agenda-conteo"><?= (int)$dia['numero'] ?> citas del filtro</p>
<p class="agenda-horarios">Atención: <?= $dia['franjas']?e(implode(', ',array_map(static fn($h)=>$h['inicio']->format('H:i').'–'.$h['fin']->format('H:i'),$dia['franjas']))):'sin horarios activos' ?>.</p></header>
<?php if($dia['numero']===0): ?><p class="agenda-sin-citas" role="status">Sin citas para este día y filtro.</p><?php endif; ?>
<ol class="agenda-eventos">
<?php foreach($dia['eventos'] as $evento): $esCita=$evento['tipo']==='cita'; ?>
<li class="agenda-evento agenda-<?= e($evento['tipo']) ?><?= $esCita&&$evento['cita']['estado']==='cancelada'?' agenda-cancelada':'' ?>" <?= $esCita?'data-agenda-cita="'.(int)$evento['cita']['id'].'"':'' ?>>
<p class="agenda-hora"><?= e($hora($evento['inicio'])) ?>–<?= $esCita?e($hora($evento['fin'])).($evento['fin']->format('Y-m-d')!==$dia['fecha']?' (+1 día)':''):($evento['fin']->format('Y-m-d')===$dia['fecha']?e($hora($evento['fin'])):'24:00') ?></p>
<?php if($esCita): $c=$evento['cita']; ?>
<span class="status-pill appointment-<?= e($c['estado']) ?>"><?= e(Cita::ESTADOS[$c['estado']]) ?></span>
<p class="agenda-paciente"><?= e($c['paciente_apellidos'].', '.$c['paciente_nombres']) ?></p><p><?= e($c['especialidad']??'Especialidad sin registrar') ?></p>
<?php if($c['estado']==='cancelada'): ?><p class="agenda-aviso">Registro histórico cancelado; no reserva el horario.</p><?php endif; ?>
<?php if(!$evento['cubierta']): ?><p class="agenda-aviso">Fuera de la atención configurada actualmente.</p><?php endif; ?>
<a class="text-link" href="/citas/<?= (int)$c['id'] ?>">Ver <?= e($c['codigo']) ?></a>
<?php else: ?><p><?= e(['fuera'=>'Fuera de atención','vacio'=>'Sin cita registrada','oculto'=>'Intervalo con cita fuera del día o filtro'][$evento['tipo']]) ?></p><?php endif; ?>
</li><?php endforeach; ?></ol></section><?php endforeach; ?></div>
<?php endif; ?>
<?php endif; ?>
