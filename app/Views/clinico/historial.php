<?php use App\Models\PerfilClinico; require __DIR__.'/cabecera.php'; ?>
<section class="panel clinico-panel"><h2>Revisiones del registro de alergia</h2><p>Se conservan las versiones anteriores; estos registros son clínicos y están separados del historial administrativo de citas.</p>
<?php foreach($revisiones as $r): ?><article class="clinico-alergia"><h3>Versión <?= (int)$r['version'] ?> · <?= e(PerfilClinico::EVENTOS[$r['evento']]) ?></h3><p>Usuario responsable #<?= (int)$r['usuario_id'] ?> · <?= e($fechaClinica($r['cambiado_en'])) ?> · America/La_Paz</p>
<dl class="clinico-resumen"><?php foreach(['sustancia'=>'Sustancia','reaccion'=>'Reacción','observaciones'=>'Observaciones','estado'=>'Estado','motivo_cambio'=>'Motivo del cambio'] as $campo=>$label): ?><dt><?= e($label) ?></dt><dd><?= e($r[$campo]??'No registrado') ?></dd><?php endforeach; ?></dl></article><?php endforeach; ?>
<a class="text-link" href="<?= $baseClinica ?>/alergias">Volver a alergias</a></section>
