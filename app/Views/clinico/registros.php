<?php
use App\Models\RegistroClinico;
require __DIR__.'/cabecera.php';
$rutaRegistros=$baseClinica.'/'.$tipo;
$rotulos=$tipo==='antecedentes'
    ?['categoria'=>'Categoría','descripcion'=>'Descripción','fecha_evento'=>'Fecha del evento','fecha_aproximada'=>'Fecha aproximada','observaciones'=>'Observaciones','estado'=>'Estado del registro']
    :['estudio'=>'Estudio o análisis','fecha_muestra'=>'Fecha de muestra o realización','fecha_resultado'=>'Fecha del resultado','resultado_texto'=>'Resultado informado','valor'=>'Valor numérico informado','unidad'=>'Unidad informada','rango_referencia'=>'Rango de referencia informado por el laboratorio','laboratorio'=>'Laboratorio o institución de origen','observaciones'=>'Observaciones del médico','estado'=>'Estado del registro'];
$mostrarValor=static function(string $campo,mixed $valor):string{
    if($valor===null||$valor===''){return 'No informado';}
    if($campo==='fecha_aproximada'){return $valor?'Sí':'No';}
    if($campo==='categoria'){return RegistroClinico::CATEGORIAS[$valor]??'Otro';}
    if($campo==='estado'){return $valor==='activo'?'Activo':'Inactivo';}
    return (string)$valor;
};
?>
<p class="notice">Información registrada por el profesional; no se interpreta automáticamente. Los estados corresponden al registro y no confirman ausencia ni resolución de una condición.</p>
<?php if($accion==='historial'): ?>
<section class="panel clinico-panel"><h2>Revisiones del <?= e($definicion['singular']) ?></h2><p>Cada versión conserva una instantánea de los campos. No son archivos de imagen ni adjuntos. La versión anterior permanece al rectificar.</p>
<?php foreach($revisiones as $r): ?><article class="clinico-alergia" data-cu24-revision="<?= (int)$r['version'] ?>"><h3>Versión <?= (int)$r['version'] ?> · <?= e(RegistroClinico::EVENTOS[$r['evento']]) ?><?= (int)$r['version']===(int)$registro['version']?' · Actual':' · Anterior' ?></h3>
<p>Usuario responsable #<?= (int)$r['usuario_id'] ?> · <?= e($fechaClinica($r['cambiado_en'])) ?> · America/La_Paz</p>
<dl class="clinico-resumen"><?php foreach($rotulos as $campo=>$rotulo): ?><dt><?= e($rotulo) ?></dt><dd><?= e($mostrarValor($campo,$r[$campo])) ?></dd><?php endforeach; ?><dt>Motivo de rectificación</dt><dd><?= e($r['motivo_cambio']??'Registro inicial') ?></dd></dl></article><?php endforeach; ?></section>
<?php elseif($registro!==null&&$accion!=='editar'): ?>
<section class="panel clinico-panel"><h2>Detalle del <?= e($definicion['singular']) ?></h2><dl class="clinico-resumen"><?php foreach($rotulos as $campo=>$rotulo): ?><dt><?= e($rotulo) ?></dt><dd><?= e($mostrarValor($campo,$registro[$campo])) ?></dd><?php endforeach; ?></dl>
<p>Versión <?= (int)$registro['version'] ?>. Creado por usuario #<?= (int)$registro['creado_por_usuario_id'] ?> · <?= e($fechaClinica($registro['creado_en'])) ?>. Último editor #<?= (int)$registro['actualizado_por_usuario_id'] ?> · <?= e($fechaClinica($registro['actualizado_en'])) ?>.</p>
<nav class="clinico-nav"><a href="<?= $rutaRegistros ?>/<?= (int)$registro['id'] ?>/editar">Rectificar o cambiar estado</a><a href="<?= $rutaRegistros ?>/<?= (int)$registro['id'] ?>/historial">Consultar revisiones</a></nav></section>
<?php else: ?>
<?php if($registro===null): ?><section class="panel clinico-panel"><h2><?= e($definicion['titulo']) ?> registrados</h2>
<?php if(!$registros): ?><p role="status" data-cu24-vacio>No hay <?= $tipo==='antecedentes'?'antecedentes':'resultados de laboratorio' ?> registrados. Esto no confirma ausencia de enfermedades ni de estudios previos.</p><?php endif; ?>
<?php foreach($registros as $r): ?><article class="clinico-alergia" data-cu24-registro="<?= (int)$r['id'] ?>"><h3><?= e($tipo==='antecedentes'?RegistroClinico::CATEGORIAS[$r['categoria']]:$r['estudio']) ?> · <?= e($mostrarValor('estado',$r['estado'])) ?></h3>
<p><?= e($tipo==='antecedentes'?$r['descripcion']:$r['resultado_texto']) ?></p>
<p class="field-hint"><?= $tipo==='antecedentes'?'Fecha del evento':'Fecha del estudio/resultado' ?>: <?= e(($tipo==='antecedentes'?$r['fecha_evento']:($r['fecha_resultado']??$r['fecha_muestra']))??'Desconocida') ?><?= $tipo==='antecedentes'&&$r['fecha_aproximada']?' (aproximada)':'' ?> · Versión <?= (int)$r['version'] ?></p>
<nav class="clinico-nav"><a href="<?= $rutaRegistros ?>/<?= (int)$r['id'] ?>">Ver detalle</a><a href="<?= $rutaRegistros ?>/<?= (int)$r['id'] ?>/editar">Rectificar o cambiar estado</a><a href="<?= $rutaRegistros ?>/<?= (int)$r['id'] ?>/historial">Consultar revisiones</a></nav></article><?php endforeach; ?></section><?php endif; ?>
<?php require __DIR__.'/registro-formulario.php'; ?>
<?php endif; ?>
<?php if($registro!==null): ?><a class="text-link" href="<?= $rutaRegistros ?>">Volver al listado</a><?php endif; ?>
