// Solo intenciones cerradas; no se genera una URL desde la transcripción.
const RUTAS = new Set(['/dashboard', '/agenda', '/agenda/diaria', '/citas', '/reportes/citas', '/pacientes', '/mis-citas', '/mis-citas?periodo=proximas', '/mis-citas/historial', '/mis-citas/nueva']);
export function normalizar(texto) {
    if (typeof texto !== 'string' || texto.length > 160) return '';
    return texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim().replace(/^[¿¡]+|[.!?¿¡]+$/gu, '').trim().replace(/\s+/gu, ' ');
}
export function resolver(texto, catalogo) {
    const frase = normalizar(texto);
    if (!frase || !Array.isArray(catalogo)) return null;
    return catalogo.find(c => c && Array.isArray(c.frases) && c.frases.some(f => normalizar(f) === frase)
        && (RUTAS.has(c.ruta) || (c.ruta === null && ['ayuda', 'comandos'].includes(c.id)))) ?? null;
}

export function montarVoz(panel, entorno = window) {
    let catalogo;
    try { catalogo = JSON.parse(panel.dataset.catalogo); } catch { return null; }
    const q = s => panel.querySelector(s);
    const consentimiento = q('[data-voz-consentimiento]'), iniciar = q('[data-voz-iniciar]'), detener = q('[data-voz-detener]');
    const idioma = q('[data-voz-idioma]'), estado = q('[data-voz-estado]'), reconocido = q('[data-voz-reconocido]');
    const texto = q('[data-voz-texto]'), seleccion = q('[data-voz-seleccion]');
    const API = typeof entorno.SpeechRecognition === 'function' ? entorno.SpeechRecognition : entorno.webkitSpeechRecognition;
    const compatible = typeof API === 'function' && entorno.isSecureContext === true;
    let activa = null, limite = null;
    const actualizar = () => {
        iniciar.disabled = !compatible || !consentimiento.checked || activa !== null;
        detener.disabled = activa === null; idioma.disabled = activa !== null;
        panel.dataset.escuchando = String(activa !== null);
    };
    const parar = (mensaje = 'Micrófono inactivo.', limpiar = true) => {
        const anterior = activa; activa = null; entorno.clearTimeout(limite); limite = null;
        if (anterior) { try { anterior.abort(); } catch {} }
        if (limpiar) { reconocido.textContent = ''; texto.value = ''; seleccion.value = ''; }
        estado.textContent = mensaje; actualizar();
    };
    const ejecutar = valor => {
        parar('Escucha finalizada.', false);
        const comando = resolver(valor, catalogo);
        reconocido.textContent = typeof valor === 'string' && valor.length <= 160 ? valor : '';
        texto.value = ''; seleccion.value = '';
        if (!comando) { estado.textContent = 'Comando no reconocido. Usa un ejemplo disponible; no se realizó ninguna acción.'; return; }
        if (comando.ruta === null) {
            estado.textContent = 'Consulta los comandos disponibles para tu cuenta.';
            q('[data-voz-ayuda]').focus(); return;
        }
        parar('Abriendo la opción autorizada.');
        entorno.location.assign(comando.ruta);
    };
    q('[data-voz-compatibilidad]').textContent = compatible
        ? 'El navegador expone una API de voz. Su disponibilidad efectiva, idioma y permiso se comprueban al iniciar; no se garantiza el reconocimiento.'
        : 'La voz no está disponible en este navegador o contexto. Usa el campo, selector o enlaces; no necesitas micrófono.';
    consentimiento.addEventListener('change', () => { if (!consentimiento.checked) parar('Escucha cancelada.'); actualizar(); });
    iniciar.addEventListener('click', () => {
        if (!compatible || !consentimiento.checked || activa !== null || !panel.open) return;
        try {
            const sesion = new API(); activa = sesion;
            sesion.lang = idioma.value === 'es-ES' ? 'es-ES' : 'es-BO';
            sesion.continuous = false; sesion.interimResults = false; sesion.maxAlternatives = 1;
            reconocido.textContent = ''; texto.value = ''; seleccion.value = '';
            sesion.onstart = () => { if (activa === sesion) estado.textContent = 'Escuchando un comando. Puedes detener y descartar.'; };
            sesion.onresult = evento => {
                if (activa !== sesion) return;
                if (!panel.open || !consentimiento.checked || panel.ownerDocument.hidden) { parar(); return; }
                const resultado = evento.results?.[evento.resultIndex ?? 0];
                if (resultado?.isFinal === false) return;
                ejecutar(resultado?.[0]?.transcript ?? '');
            };
            sesion.onerror = evento => {
                if (activa !== sesion) return;
                const mensajes = {
                    'not-allowed': 'Permiso de micrófono denegado. Puedes usar la alternativa manual.',
                    'service-not-allowed': 'El servicio de voz no está permitido. Usa la alternativa manual.',
                    'audio-capture': 'No hay un micrófono disponible. Usa la alternativa manual.',
                    'no-speech': 'No se detectó un comando. Inicia otra sesión si lo deseas o usa texto.',
                    'network': 'El servicio de voz no está disponible. Usa la alternativa manual.',
                    'language-not-supported': 'Este idioma no está disponible. Puedes elegir Español (España) e iniciar de nuevo, o usar texto.',
                };
                parar(mensajes[evento.error] ?? 'No se pudo reconocer el comando. Usa la alternativa manual.');
            };
            sesion.onend = () => { if (activa === sesion) parar('La escucha terminó sin un comando. Usa texto o inicia otra sesión.'); };
            estado.textContent = 'Preparando una escucha corta; esperando permiso del navegador.'; actualizar();
            limite = entorno.setTimeout(() => { if (activa === sesion) parar('Tiempo de escucha agotado. El micrófono se detuvo.'); }, 12000);
            sesion.start();
        } catch { parar('No se pudo iniciar la voz. Puedes usar la alternativa manual.'); }
    });
    detener.addEventListener('click', () => parar('Escucha detenida y comando descartado.'));
    q('[data-voz-manual]').addEventListener('submit', evento => {
        evento.preventDefault();
        // El texto siempre se evalúa completo. No se ignora en favor del selector.
        const elegido = catalogo.find(c => c.id === seleccion.value);
        ejecutar(texto.value.trim() !== '' ? texto.value : (elegido?.frases?.[0] ?? ''));
    });
    const descartar = () => { parar(); consentimiento.checked = false; actualizar(); };
    panel.addEventListener('toggle', () => { if (!panel.open) descartar(); });
    entorno.addEventListener('pagehide', descartar);
    panel.ownerDocument.addEventListener('visibilitychange', () => { if (panel.ownerDocument.hidden) descartar(); });
    panel.ownerDocument.addEventListener('click', evento => { if (evento.target.closest?.('a[href]')) descartar(); }, true);
    panel.ownerDocument.addEventListener('submit', evento => {
        if (evento.target.matches?.('form[action="/logout"]')) descartar();
    }, true);
    actualizar();
    return { parar: descartar };
}
if (typeof document !== 'undefined') document.querySelectorAll('[data-voz]').forEach(panel => montarVoz(panel));
