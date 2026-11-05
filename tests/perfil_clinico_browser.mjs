import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';
import { join, dirname, basename, resolve } from 'node:path';

// Metadatos ficticios por stdin; no colocar credenciales en argumentos ni logs.
let input = ''; for await (const chunk of process.stdin) input += chunk;
const data = JSON.parse(input);
const profile = await mkdtemp(join(tmpdir(), 'clinica-cu23-browser-'));
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
        const agente = 'CU23_BROWSER_FICTICIO_' + modo;
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 500 });
        await navigation(() => send('Page.navigate', { url: data.base + base + '/perfil-clinico' }));
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Perfil sin desbordamiento ' + modo);
        check(await evaluate('!!document.querySelector("[data-clinico-perfil]")'), 'Formulario clínico autorizado ' + modo);
        check(await evaluate('document.querySelector("[name=grupo_sanguineo]").value===""'), 'Grupo desconocido permanece vacío ' + modo);
        check(await evaluate('document.querySelector("[data-clinico-perfil] button").getBoundingClientRect().height>=44'), 'Botón táctil de perfil ' + modo);
        await navigation(() => evaluate(`const f=document.querySelector('[data-clinico-perfil]');f.querySelector('[name=observaciones]').value='CU23_BROWSER_RESUMEN_FICTICIO';f.querySelector('[name=confirmar]').checked=true;f.requestSubmit();`));
        check(await evaluate('location.search==="?guardado=1"&&!!document.querySelector("[data-clinico-perfil]")'), 'Guardado y redirección de perfil ' + modo);
        await navigation(() => send('Page.navigate', { url: data.base + base + '/alergias' }));
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Alergias sin desbordamiento ' + modo);
        await navigation(() => evaluate(`const f=document.querySelector('[data-clinico-alergia-form]');f.querySelector('[name=sustancia]').value=${JSON.stringify(agente)};f.querySelector('[name=reaccion]').value='<b>CU23_BROWSER_REACCION_FICTICIA</b>';f.querySelector('[name=confirmar]').checked=true;f.requestSubmit();`));
        const buscar = `[...document.querySelectorAll('[data-clinico-alergia]')].find(e=>e.querySelector('h3').textContent.includes(${JSON.stringify(agente)}))`;
        check(await evaluate(`!!(${buscar})&&!document.querySelector('[data-clinico-alergia] b')`), 'Registro y escape real en DOM ' + modo);
        await navigation(() => evaluate(`(${buscar}).querySelector('a[href$="/editar"]').click()`));
        check(await evaluate('!!document.querySelector("[name=motivo_cambio]")&&!!document.querySelector("[name=version]")'), 'Rectificación con motivo y versión ' + modo);
        await navigation(() => evaluate(`const f=document.querySelector('[data-clinico-alergia-form]');f.querySelector('[name=estado]').value='inactiva';f.querySelector('[name=motivo_cambio]').value='CU23_BROWSER_MOTIVO_FICTICIO';f.querySelector('[name=confirmar]').checked=true;f.requestSubmit();`));
        check(await evaluate(`(${buscar}).querySelector('.status-pill').textContent==='Inactiva'`), 'Cambio de estado confirmado ' + modo);
        await navigation(() => evaluate(`(${buscar}).querySelector('a[href$="/historial"]').click()`));
        check(await evaluate('document.body.innerText.includes("Versión 1")&&document.body.innerText.includes("Versión 2")&&document.body.innerText.includes("Usuario responsable")'), 'Revisiones y responsable visibles ' + modo);
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Historial sin desbordamiento ' + modo);
        check(await evaluate('!window.CU23_XSS'), 'Sin ejecución de texto de prueba ' + modo);
    }
    check(requests.every(u => new URL(u).origin === new URL(data.base).origin), 'Sin solicitudes clínicas a terceros');
    check(requests.every(u => !u.includes('CU23_BROWSER_')), 'Sin valores clínicos en URL');
    result = { assertions, noClinicalCaptures: true };
    await send('Browser.close');
} catch (error) {
    result = { error: error.message, assertions }; process.exitCode = 1;
} finally {
    if (socket?.readyState === WebSocket.OPEN) socket.close();
    if (browser && browser.exitCode === null) { await pause(500); if (browser.exitCode === null) browser.kill(); }
    if (dirname(resolve(profile)) === resolve(tmpdir()) && basename(profile).startsWith('clinica-cu23-browser-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }).catch(() => {});
    }
}
process.stdout.write(JSON.stringify(result));
