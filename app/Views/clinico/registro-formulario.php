<?php
use App\Models\RegistroClinico;
$valores=$entrada??$registro??[];
$limites=['descripcion'=>1000,'observaciones'=>500,'estudio'=>120,'resultado_texto'=>2000,'unidad'=>40,'rango_referencia'=>250,'laboratorio'=>160,'motivo_cambio'=>250,'valor'=>20];
?>
<section class="panel clinico-panel"><h2><?= $registro?'Rectificar o cambiar estado':'Registrar' ?> <?= e($definicion['singular']) ?></h2>
<form method="post" action="<?= $rutaRegistros ?><?= $registro?'/'.(int)$registro['id'].'/actualizar':'' ?>" class="clinico-form" autocomplete="off" data-cu24-form="<?= e($tipo) ?>">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><?php if($registro): ?><input type="hidden" name="version" value="<?= e((string)($valores['version']??$registro['version'])) ?>"><?php endif; ?>
<?php foreach($rotulos as $campo=>$rotulo): if($campo==='estado'&&!$registro){continue;} $valor=$valores[$campo]??''; ?>
<div class="field-group"><label for="cu24-<?= e($campo) ?>"><?= e($rotulo) ?></label>
<?php if($campo==='categoria'): ?><select id="cu24-categoria" name="categoria" required><option value="">Selecciona una categoría</option><?php foreach(RegistroClinico::CATEGORIAS as $v=>$etiqueta): ?><option value="<?= e($v) ?>" <?= $valor===$v?'selected':'' ?>><?= e($etiqueta) ?></option><?php endforeach; ?></select>
<?php elseif($campo==='estado'): ?><select id="cu24-estado" name="estado"><option value="activo" <?= $valor==='activo'?'selected':'' ?>>Activo</option><option value="inactivo" <?= $valor==='inactivo'?'selected':'' ?>>Inactivo</option></select>
<?php elseif($campo==='fecha_aproximada'): $valor=is_bool($valor)?($valor?'1':'0'):$valor; ?><select id="cu24-fecha_aproximada" name="fecha_aproximada"><option value="">Sin fecha registrada</option><option value="0" <?= $valor==='0'?'selected':'' ?>>Fecha conocida</option><option value="1" <?= $valor==='1'?'selected':'' ?>>Fecha aproximada indicada expresamente</option></select><p class="field-hint">Si solo conoces una época o un año, deja la fecha vacía y acláralo en observaciones, sin inventar día o mes.</p>
<?php elseif(str_starts_with($campo,'fecha_')): ?><input id="cu24-<?= e($campo) ?>" name="<?= e($campo) ?>" type="date" value="<?= e((string)$valor) ?>"><p class="field-hint">Opcional: deja vacío si se desconoce. No se aceptan fechas futuras.</p>
<?php elseif(in_array($campo,['descripcion','resultado_texto','observaciones'],true)): ?><textarea id="cu24-<?= e($campo) ?>" name="<?= e($campo) ?>" maxlength="<?= $limites[$campo] ?>" rows="3" <?= $campo!=='observaciones'?'required':'' ?>><?= e((string)$valor) ?></textarea>
<?php else: ?><input id="cu24-<?= e($campo) ?>" name="<?= e($campo) ?>" maxlength="<?= $limites[$campo] ?>" value="<?= e((string)$valor) ?>" <?= $campo==='estudio'?'required':'' ?> <?= $campo==='valor'?'inputmode="decimal"':'' ?>><?php endif; ?>
<?php if($campo==='valor'): ?><p class="field-hint">Opcional: decimal con punto, hasta doce cifras enteras y seis decimales. No se calcula normalidad ni se transforma un resultado cualitativo en número.</p><?php endif; ?>
<?php if($campo==='rango_referencia'): ?><p class="field-hint">Solo información proporcionada por ese laboratorio; no es un rango universal.</p><?php endif; ?>
</div><?php endforeach; ?>
<?php if($registro): ?><div class="field-group"><label for="cu24-motivo_cambio">Motivo de rectificación o cambio de estado</label><input id="cu24-motivo_cambio" name="motivo_cambio" maxlength="250" value="<?= e($valores['motivo_cambio']??'') ?>" required></div><p class="field-hint">Las versiones anteriores se conservan. Inactivar no elimina el registro ni implica una conclusión médica.</p><?php endif; ?>
<label class="clinico-confirmar"><input type="checkbox" name="confirmar" value="1" required>Confirmo la información y <?= $registro?'el motivo de esta rectificación o cambio':'el registro' ?>.</label>
<button class="button" type="submit"><?= $registro?'Guardar revisión':'Registrar' ?></button></form></section>
