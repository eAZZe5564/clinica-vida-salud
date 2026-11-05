<?php
use App\Models\PerfilClinico;
use App\Models\HorarioAtencion;
$baseClinica='/pacientes/'.(int)$paciente['id'];
$fechaClinica=static fn(string $d):string=>(new DateTimeImmutable($d))->setTimezone(HorarioAtencion::zona())->format('d/m/Y H:i:s');
?>
<section class="page-heading"><p class="eyebrow">Atención médica · <?= isset($tipo)?'CU24':'CU23' ?></p><h1><?= e($clinicoTitulo??'Perfil clínico y alergias') ?></h1>
<p class="lead"><?= e($paciente['apellidos'].', '.$paciente['nombres']) ?> · <?= e(App\Models\Paciente::codigo((int)$paciente['id'])) ?></p></section>
<nav class="clinico-nav" aria-label="Información clínica del paciente"><a class="button button-secondary" href="<?= $baseClinica ?>/perfil-clinico">Perfil básico</a><a class="button button-secondary" href="<?= $baseClinica ?>/alergias">Alergias</a><a class="button button-secondary" href="<?= $baseClinica ?>/antecedentes">Antecedentes</a><a class="button button-secondary" href="<?= $baseClinica ?>/laboratorios">Laboratorio</a><a class="text-link" href="/agenda/diaria">Volver a mi agenda</a></nav>
<p class="notice">Acceso limitado a tu relación asistencial del día en America/La_Paz. No registrado no significa ausencia de una condición. No se generan diagnósticos automáticos, tratamientos ni recetas.</p>
<?php if(($_GET['guardado']??null)==='1'): ?><p class="notice" role="status">La información se guardó correctamente.</p><?php endif; ?>
<?php if($errores): ?><div class="notice notice-error" role="alert"><p>No se guardaron los cambios. Revisa lo siguiente:</p><ul><?php foreach($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
