import { spawn } from 'node:child_process';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname, basename, resolve } from 'node:path';

// Metadatos ficticios por stdin; no colocar credenciales en argumentos ni logs.
let input = ''; for await (const chunk of process.stdin) input += chunk;
const data = JSON.parse(input);
const profile = await mkdtemp(join(tmpdir(), 'clinica-citas-browser-'));
const port = 45001 + Math.floor(Math.random() * 10000);
let browser, socket, id = 0, assertions = 0;
const pending = new Map(), listeners = new Map();
const pause = (ms) => new Promise((r) => setTimeout(r, ms));
const check = (value, message) => { if (!value) throw new Error(message); assertions++; };
let result;
try {
    browser = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
        '--headless=new', '--no-first-run', '--disable-gpu', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank',
    ], { windowsHide: true, stdio: 'ignore' });
    let target;
    for (let i = 0; i < 100; i++) {
        try { target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((x) => x.type === 'page'); if (target) break; } catch {}
        await pause(100);
    }
    if (!target) throw new Error('No se pudo conectar al navegador local');
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((r, reject) => { socket.onopen = r; socket.onerror = reject; });
    socket.onmessage = ({ data: raw }) => {
        const message = JSON.parse(raw);
        if (message.id) { const item = pending.get(message.id); pending.delete(message.id); if (message.error) item?.reject(new Error(message.error.message)); else item?.resolve(message.result); }
        else { for (const fn of listeners.get(message.method) ?? []) fn(message.params); }
    };
    const send = (method, params = {}) => new Promise((resolve, reject) => { const request = ++id; pending.set(request, { resolve, reject }); socket.send(JSON.stringify({ id: request, method, params })); });
    const evaluate = async (expression) => {
        const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture: true });
        if (response.exceptionDetails) throw new Error('Falló una acción de navegador');
        return response.result?.value;
    };
    const navigation = async (action) => {
        let handler, timer;
        const loaded = new Promise((r, reject) => {
            handler = () => { clearTimeout(timer); listeners.get('Page.loadEventFired').delete(handler); r(); };
            if (!listeners.has('Page.loadEventFired')) listeners.set('Page.loadEventFired', new Set());
            listeners.get('Page.loadEventFired').add(handler);
            timer = setTimeout(() => { listeners.get('Page.loadEventFired').delete(handler); reject(new Error('La navegación no terminó')); }, 15000);
        });
        await action(); await loaded;
    };
    await send('Page.enable'); await send('Runtime.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
    await navigation(() => send('Page.navigate', { url: `${data.base}/login` }));
    await navigation(() => evaluate(`document.querySelector('[name="correo"]').value=${JSON.stringify(data.correo)};document.querySelector('[name="contrasena"]').value=${JSON.stringify(data.password)};document.querySelector('form[action="/login"]').requestSubmit();`));
    check(await evaluate('location.pathname==="/dashboard"'), 'Login ficticio en navegador');
    if (data.consulta) {
        // CU14 utiliza el mismo navegador real; toda la navegación es de lectura.
        const directory = resolve('tests/artifacts/consulta-citas'); await mkdir(directory, { recursive: true });
        const captures = [];
        const screenshot = async (name) => {
            const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
            const path = join(directory, name); await writeFile(path, Buffer.from(shot.data, 'base64')); captures.push(path);
        };
        const query = new URLSearchParams({ medico_id: String(data.medico), estado: 'cancelada', desde: '2010-01-01', hasta: '2010-01-03' });
        await navigation(() => send('Page.navigate', { url: `${data.base}/citas?${query}` }));
        check(await evaluate('document.querySelectorAll("[data-cita-id]").length===3'), 'Tres citas obtenidas por filtros reales');
        check(await evaluate('document.querySelector("[name=desde]").value==="2010-01-01"&&document.querySelector("[name=estado]").value==="cancelada"'), 'Filtros visibles conservados');
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Listado escritorio sin desbordamiento');
        check(await evaluate('getComputedStyle(document.querySelector("thead")).position!=="absolute"'), 'Tabla visible en escritorio');
        check(await evaluate('[1,5,6,7].every(n=>{const r=document.createRange();r.selectNodeContents(document.querySelector(`[data-cita-id] td:nth-child(${n})`));return r.getClientRects().length===1})'), 'Código, fecha y horas completos en una línea');
        await screenshot('listado-escritorio.png');
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Listado móvil sin desbordamiento');
        check(await evaluate('getComputedStyle(document.querySelector("thead")).position==="absolute"&&getComputedStyle(document.querySelector("td")).display==="flex"&&getComputedStyle(document.querySelector("td"),"::before").content!=="none"'), 'Tarjetas etiquetadas en móvil');
        await screenshot('listado-movil.png');
        await navigation(() => evaluate('document.querySelector("[data-cita-id] a").click()'));
        check(await evaluate('document.body.innerText.includes("Detalle de cita")&&document.body.innerText.includes("23:30–00:00")'), 'Detalle móvil con horario local');
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1&&getComputedStyle(document.querySelector(".detail-grid")).gridTemplateColumns.split(" ").length===1'), 'Detalle móvil legible en una columna');
        await screenshot('detalle-movil.png');
        await navigation(() => evaluate('document.querySelector(".form-actions a").click()'));
        check(await evaluate('document.querySelectorAll("[data-cita-id]").length===3&&location.search.includes("desde=2010-01-01")'), 'Volver conserva rango y resultados');
        await navigation(() => send('Page.navigate', { url: `${data.base}/citas` }));
        await navigation(() => evaluate(`document.querySelector('[name=codigo]').value=${JSON.stringify(data.codigo)};document.querySelector('.appointment-filter-grid').requestSubmit();`));
        check(await evaluate(`document.querySelectorAll('[data-cita-id]').length===1&&document.querySelector('[data-cita-id]').dataset.citaId===${JSON.stringify(String(data.reserva))}`), 'Búsqueda de reserva CU13 por formulario');
        await navigation(() => evaluate('document.querySelector("[data-cita-id] a").click()'));
        check(await evaluate('document.body.innerText.includes("MOTIVO_PRIVADO_CU14 <script>")&&!document.querySelector(".detail-grid script")'), 'Motivo administrativo escapado sin ejecución');
        await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
        await screenshot('detalle-escritorio.png');
        await navigation(() => evaluate('document.querySelector("form[action=\\"/logout\\"]").requestSubmit()'));
        await navigation(() => send('Page.navigate', { url: `${data.base}/login` }));
        await navigation(() => evaluate(`document.querySelector('[name=correo]').value=${JSON.stringify(data.medicoCorreo)};document.querySelector('[name=contrasena]').value=${JSON.stringify(data.password)};document.querySelector('form[action="/login"]').requestSubmit();`));
        await navigation(() => send('Page.navigate', { url: `${data.base}/citas/${data.reserva}` }));
        check(await evaluate('document.body.innerText.includes("Detalle de cita")&&!document.body.innerText.includes("MOTIVO_PRIVADO")&&!document.body.innerText.includes("Registrada por")'), 'Médico ve detalle propio con datos básicos');
        await navigation(() => send('Page.navigate', { url: `${data.base}/citas/${data.ajena}` }));
        check(await evaluate('document.body.innerText.includes("Página no encontrada")&&!document.body.innerText.includes("PACIENTE_AGENDA_AJENA")'), 'Acceso manual a cita ajena bloqueado en navegador');
        result = { assertions, captures };
    } else {
    await navigation(() => send('Page.navigate', { url: `${data.base}/citas/nueva?q=${data.sufijo}` }));
    check(await evaluate('!!document.querySelector("[data-booking-options]")'), 'Formulario real cargado');
    for (const [field, value] of [['paciente_id', data.paciente], ['especialidad_id', data.especialidad], ['medico_id', data.medico], ['fecha', data.fecha]]) {
        await navigation(() => evaluate(`document.querySelector('[name="${field}"]').value=${JSON.stringify(String(value))};document.querySelector('[name="${field}"]').dispatchEvent(new Event('change',{bubbles:true}));`));
        check(await evaluate(`document.querySelector('[name="${field}"]').value===${JSON.stringify(String(value))}`), `Actualización de ${field}`);
    }
    check(await evaluate('document.querySelectorAll("input[name=hora_inicio]").length===8'), 'Ocho turnos reales en navegador');
    await evaluate('document.querySelector("input[name=hora_inicio][value=\\"08:00\\"]").checked=true;document.querySelector("[name=motivo]").value="Motivo ficticio de prueba visual";');
    const directory = resolve('tests/artifacts/citas'); await mkdir(directory, { recursive: true });
    const screenshot = async (name) => { const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }); const path = join(directory, name); await writeFile(path, Buffer.from(shot.data, 'base64')); return path; };
    const desktop = await screenshot('formulario-escritorio.png');
    check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Escritorio sin desbordamiento horizontal');
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    const mobile = await screenshot('formulario-movil.png');
    check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Móvil sin desbordamiento horizontal');
    check(await evaluate('getComputedStyle(document.querySelector(".booking-slots")).gridTemplateColumns.split(" ").length===2'), 'Turnos en dos columnas legibles en móvil');
    await navigation(() => evaluate('document.querySelector("[data-booking-options]").requestSubmit(document.querySelector("button[value=previsualizar]"));'));
    check(await evaluate('!!document.querySelector("[data-booking-confirm]")&&document.body.innerText.includes("08:00–08:30")'), 'Revisión previa con turno seleccionado');
    await navigation(() => evaluate('const form=document.querySelector("[data-booking-confirm]");form.requestSubmit();form.requestSubmit();'));
    check(await evaluate('location.pathname.startsWith("/citas/confirmacion/")&&document.body.innerText.includes("Cita programada")'), 'Confirmación real tras doble intento de envío');
    check(await evaluate('!document.body.innerText.includes("Motivo ficticio de prueba visual")'), 'Confirmación sin motivo clínico');
    const receipt = await screenshot('confirmacion-movil.png');
    await navigation(() => send('Page.reload'));
    check(await evaluate('document.body.innerText.includes("Cita programada")'), 'Recarga de recibo por GET');
    result = { assertions, desktop, mobile, receipt };
    }
    await send('Browser.close');
} catch (error) {
    result = { error: error.message, assertions }; process.exitCode = 1;
} finally {
    if (socket?.readyState === WebSocket.OPEN) socket.close();
    if (browser && browser.exitCode === null) { await pause(500); if (browser.exitCode === null) browser.kill(); }
    // Verificar el destino absoluto antes de borrar recursivamente el perfil temporal.
    if (dirname(resolve(profile)) === resolve(tmpdir()) && basename(profile).startsWith('clinica-citas-browser-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }).catch(() => {});
    }
}
process.stdout.write(JSON.stringify(result));
