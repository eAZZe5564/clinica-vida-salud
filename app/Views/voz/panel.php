<?php
// Solo se incluye para el actor autenticado de layout, nunca para una cuenta editada.
$vozVinculado=false;
if($actor['rol']==='paciente'){
    $vozPaciente=(new \App\Repositories\PortalPacienteRepository(\App\Config\Database::connection()))->paciente((int)$actor['id']);
    $vozVinculado=$vozPaciente!==null&&(bool)$vozPaciente['activo'];
}
$vozCatalogo=\App\Services\CatalogoVoz::paraRol($actor['rol'],$vozVinculado);
?>
<details class="voz" data-voz data-catalogo="<?= e(json_encode($vozCatalogo,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>">
<summary class="voz-boton">Comandos de voz</summary>
<section class="voz-contenido" aria-labelledby="voz-titulo">
    <h2 id="voz-titulo">Navegar con comandos</h2>
    <p>Abre una pantalla autorizada. Este módulo no guarda citas ni modifica información.</p>
    <p id="voz-privacidad">Algunos navegadores envían audio a un servicio externo para reconocerlo. No podemos garantizar procesamiento local. Di solo un comando del catálogo, sin nombres, CI ni información médica. La aplicación no guarda audio ni envía o conserva transcripciones en su servidor.</p>
    <p data-voz-compatibilidad>La voz necesita JavaScript y un navegador compatible. Puedes usar los enlaces de ayuda sin micrófono.</p>
    <label class="voz-aceptacion"><input type="checkbox" data-voz-consentimiento autocomplete="off"> Entiendo el posible procesamiento externo del audio y acepto voluntariamente usar el micrófono para este comando.</label>
    <div class="voz-acciones">
        <label>Idioma<select data-voz-idioma aria-describedby="voz-idioma-nota"><option value="es-BO">Español (Bolivia)</option><option value="es-ES">Español (España)</option></select></label>
        <button type="button" class="button" data-voz-iniciar disabled>Iniciar escucha</button>
        <button type="button" class="button button-secondary" data-voz-detener disabled>Detener y descartar</button>
    </div>
    <p id="voz-idioma-nota">El soporte de cada idioma depende del navegador y su servicio. No cambiaremos de idioma ni reiniciaremos la escucha automáticamente.</p>
    <p role="status" aria-live="polite" aria-atomic="true" data-voz-estado>Micrófono inactivo.</p>
    <p>Comando reconocido: <output data-voz-reconocido aria-live="polite"></output></p>
    <form data-voz-manual autocomplete="off">
        <h3>Alternativa sin micrófono</h3>
        <label>Escribir un comando<input type="text" data-voz-texto maxlength="160" autocomplete="off" spellcheck="false"></label>
        <label>O elegir un comando<select data-voz-seleccion><option value="">Selecciona una opción</option><?php foreach($vozCatalogo as $vozComando): ?><option value="<?= e($vozComando['id']) ?>"><?= e($vozComando['ejemplo']) ?></option><?php endforeach; ?></select></label>
        <button type="submit" class="button">Abrir opción</button>
    </form>
    <?php if($actor['rol']==='paciente'&&!$vozVinculado): ?><p class="notice">Tu cuenta necesita una vinculación activa verificada para consultar o gestionar citas. Solicita atención administrativa; este panel no permite vincularte.</p><?php endif; ?>
    <section data-voz-ayuda aria-labelledby="voz-ayuda-titulo" tabindex="-1">
        <h3 id="voz-ayuda-titulo">Comandos disponibles</h3>
        <ul><?php foreach($vozCatalogo as $vozComando): ?><li><?php if($vozComando['ruta']!==null): ?><a href="<?= e($vozComando['ruta']) ?>"><?= e($vozComando['ejemplo']) ?></a><?php else: ?><?= e($vozComando['ejemplo']) ?> (muestra esta ayuda)<?php endif; ?></li><?php endforeach; ?></ul>
    </section>
    <noscript><p>La voz y el campo de comandos requieren JavaScript. Los enlaces anteriores y el menú habitual siguen disponibles.</p></noscript>
</section></details>
