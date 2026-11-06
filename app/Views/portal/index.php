<?php use App\Models\Cita; ?>
<section class="page-heading portal"><p class="eyebrow">Portal Paciente · America/La_Paz</p><h1><?= $historico?'Mi historial de citas':'Mis citas' ?></h1>
<p>Consulta y gestiona las citas de tu cuenta verificada.</p>
<nav class="portal-actions" aria-label="Mis citas"><a class="button" href="/mis-citas/nueva">Reservar cita</a><a href="/mis-citas">Próximas</a><a href="/mis-citas?periodo=historicas">Anteriores</a><a href="/mis-citas?periodo=todas&amp;estado=cancelada">Canceladas</a><a href="/mis-citas/historial">Historial completo</a></nav></section>
<section class="panel portal">
<form method="get" class="portal-grid" data-portal-filtros>
<label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
<label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
<label>Estado<select name="estado"><option value="">Todos</option><?php foreach(Cita::ESTADOS as $valor=>$nombre): ?><option value="<?= e($valor) ?>" <?= $filtros['estado']===$valor?'selected':'' ?>><?= e($nombre) ?></option><?php endforeach; ?></select></label>
<label>Período<select name="periodo"><?php foreach(['proximas'=>'Próximas','historicas'=>'Anteriores','todas'=>'Todas'] as $v=>$n): ?><option value="<?= e($v) ?>" <?= $filtros['periodo']===$v?'selected':'' ?>><?= e($n) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Filtrar</button></form>
<p><?= (int)$resultado['total'] ?> citas encontradas · Hora de La Paz</p>
<?php if(!$resultado['filas']): ?><p class="empty-state">No tienes citas en este período.</p><?php endif; ?>
<div class="portal-cards"><?php foreach($resultado['filas'] as $c): ?>
<article class="portal-card" data-portal-cita><h2><a href="/mis-citas/<?= (int)$c['id'] ?>"><?= e($c['codigo']) ?></a></h2>
<p><strong><?= e($c['inicio_local']) ?></strong> a <?= e($c['fin_local']) ?></p><p><?= e($c['medico_nombres'].' '.$c['medico_apellidos']) ?> · <?= e($c['especialidad']??'Sin especialidad registrada') ?></p>
<p class="badge"><?= e(Cita::ESTADOS[$c['estado']]) ?></p><div class="portal-actions"><a href="/mis-citas/<?= (int)$c['id'] ?>/historial">Ver detalle e historial</a>
<?php if($c['estado']==='programada'): ?><a href="/mis-citas/<?= (int)$c['id'] ?>/reprogramar">Reprogramar</a><a href="/mis-citas/<?= (int)$c['id'] ?>/cancelar">Cancelar</a><?php endif; ?></div></article>
<?php endforeach; ?></div>
<nav class="pagination" aria-label="Páginas de mis citas"><?php if($resultado['pagina']>1): ?><a href="?<?= e(http_build_query(array_filter($filtros,static fn($v,$k)=>in_array($k,['desde','hasta','estado','periodo'],true),ARRAY_FILTER_USE_BOTH)+['pagina'=>$resultado['pagina']-1])) ?>">Anterior</a><?php endif; ?>
<span>Página <?= (int)$resultado['pagina'] ?> de <?= (int)$resultado['paginas'] ?></span>
<?php if($resultado['pagina']<$resultado['paginas']): ?><a href="?<?= e(http_build_query(array_filter($filtros,static fn($v,$k)=>in_array($k,['desde','hasta','estado','periodo'],true),ARRAY_FILTER_USE_BOTH)+['pagina'=>$resultado['pagina']+1])) ?>">Siguiente</a><?php endif; ?></nav></section>
