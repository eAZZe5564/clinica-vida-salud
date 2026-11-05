import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';
import { join, dirname, basename, resolve } from 'node:path';

// Metadatos ficticios por stdin; no colocar credenciales en argumentos ni logs.
let input = ''; for await (const chunk of process.stdin) input += chunk;
const data = JSON.parse(input);
const profile = await mkdtemp(join(tmpdir(), 'clinica-cu24-browser-'));
const port = 45001 + Math.floor(Math.random() * 10000);
let browser, socket, id = 0, assertions = 0;
const pending = new Map(), listeners = new Map();
const pause = (ms) => new Promise((r) => setTimeout(r, ms));
const check = (value, message) => { if (!value) throw new Error(message); assertions++; };
let result;
try {
    browser = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
        '--headless=new', '--disable-background-networking', '--disable-sync', '--no-first-run', '--disable-gpu', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank',
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

    const requests = [];
    listeners.set('Network.requestWillBeSent', new Set([e => requests.push(e.request.url)]));
    await send('Network.enable');
    const base = '/pacientes/' + data.paciente;
    for (const [modo, width, height] of [['escritorio',1440,1000],['tablet',820,1180],['celular',390,844]]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 500 });
        for (const tipo of ['antecedentes','laboratorios']) {
            const marca = 'CU24_BROWSER_FICTICIO_' + tipo + '_' + modo;
            const campo = tipo === 'antecedentes' ? 'descripcion' : 'resultado_texto';
            await navigation(() => send('Page.navigate', { url: data.base + base + '/' + tipo }));
            check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Listado sin desbordamiento ' + tipo + modo);
            check(await evaluate('!!document.querySelector("[data-cu24-form]")'), 'Formulario autorizado ' + tipo + modo);
            check(await evaluate('document.querySelector("[data-cu24-form] button").getBoundingClientRect().height>=44'), 'Botón táctil ' + tipo + modo);
            const llenar = tipo === 'antecedentes' ? "f.querySelector('[name=categoria]').value='personal';" : "f.querySelector('[name=estudio]').value='CU24_ESTUDIO_FICTICIO';f.querySelector('[name=valor]').value='1.250000';f.querySelector('[name=unidad]').value='CU24_UNIDAD_FICTICIA';";
            await navigation(() => evaluate(`const f=document.querySelector('[data-cu24-form]');${llenar}f.querySelector('[name=${campo}]').value=${JSON.stringify('<b>' + marca + '</b>')};f.querySelector('[name=confirmar]').checked=true;f.requestSubmit();`));
            check(await evaluate('location.search==="?guardado=1"'), 'Alta con PRG ' + tipo + modo);
            const buscar = `[...document.querySelectorAll('[data-cu24-registro]')].find(e=>e.textContent.includes(${JSON.stringify(marca)}))`;
            check(await evaluate(`!!(${buscar})&&!(${buscar}).querySelector('b')`), 'Registro y escape en DOM ' + tipo + modo);
            await navigation(() => evaluate(`(${buscar}).querySelector('a[href$="/editar"]').click()`));
            check(await evaluate('!!document.querySelector("[name=motivo_cambio]")&&!!document.querySelector("[name=version]")'), 'Rectificación con motivo y versión ' + tipo + modo);
            await navigation(() => evaluate(`const f=document.querySelector('[data-cu24-form]');f.querySelector('[name=${campo}]').value=${JSON.stringify(marca + '_RECTIFICADO')};f.querySelector('[name=motivo_cambio]').value='CU24_MOTIVO_FICTICIO';f.querySelector('[name=confirmar]').checked=true;f.requestSubmit();`));
            check(await evaluate(`!!(${buscar})&&(${buscar}).textContent.includes('Versión 2')`), 'Corrección real guardada ' + tipo + modo);
            await navigation(() => evaluate(`(${buscar}).querySelector('a[href$="/historial"]').click()`));
            check(await evaluate('document.body.innerText.includes("Versión 1")&&document.body.innerText.includes("Versión 2")&&document.body.innerText.includes("Anterior")&&document.body.innerText.includes("Actual")'), 'Instantáneas anterior y actual ' + tipo + modo);
            check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Revisiones sin desbordamiento ' + tipo + modo);
            check(await evaluate('!window.CU24_XSS'), 'Sin ejecución de texto de prueba ' + tipo + modo);
        }
    }
    // Edge también notifica recursos data: internos: no son conexiones de red.
    // Cualquier solicitud con otro esquema/origen sigue fallando; las URL se revisan abajo.
    const origenesAjenos = [...new Set(requests.filter(u => new URL(u).protocol !== 'data:' && new URL(u).origin !== new URL(data.base).origin).map(u => new URL(u).origin + ' / ' + new URL(u).protocol))];
    check(origenesAjenos.length === 0, 'Solicitud de navegador fuera del origen local: ' + origenesAjenos.join(', '));
    check(requests.every(u => !u.includes('CU24_BROWSER_')), 'Sin valores clínicos en URL');
    result = { assertions, noClinicalCaptures: true };
    await send('Browser.close');
} catch (error) {
    result = { error: error.message, assertions }; process.exitCode = 1;
} finally {
    if (socket?.readyState === WebSocket.OPEN) socket.close();
    if (browser && browser.exitCode === null) { await pause(500); if (browser.exitCode === null) browser.kill(); }
    if (dirname(resolve(profile)) === resolve(tmpdir()) && basename(profile).startsWith('clinica-cu24-browser-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }).catch(() => {});
    }
}
process.stdout.write(JSON.stringify(result));
