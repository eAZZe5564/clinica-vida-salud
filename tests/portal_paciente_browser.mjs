import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';
import { join, dirname, basename, resolve } from 'node:path';

// Metadatos ficticios por stdin; no colocar credenciales en argumentos ni logs.
let input = ''; for await (const chunk of process.stdin) input += chunk;
const data = JSON.parse(input);
const profile = await mkdtemp(join(tmpdir(), 'clinica-cu25-browser-'));
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
    const login = async correo => {
        await navigation(() => send('Page.navigate', {url: data.base + '/login'}));
        await navigation(() => evaluate(`document.querySelector('[name=correo]').value=${JSON.stringify(correo)};document.querySelector('[name=contrasena]').value=${JSON.stringify(data.password)};document.querySelector('form[action="/login"]').requestSubmit();`));
        check(await evaluate('location.pathname==="/dashboard"'), 'Login de rol esperado');
    };
    const logout = async () => navigation(() => evaluate('document.querySelector("form[action=\\"/logout\\"]").requestSubmit()'));
    let modoId = 0;
    for (const [modo,width,height] of [['escritorio',1440,1000],['tablet',820,1180],['celular',390,844]]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
        const screen = async label => {
            check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Sin desbordamiento '+label+' '+modo);
            check(await evaluate('!window.CU25_XSS'), 'Sin XSS '+modo);
        };
        await navigation(() => send('Page.navigate',{url:data.base+'/mis-citas'}));
        await screen('lista');
        check(await evaluate('!!document.querySelector("a[href=\\"/mis-citas/nueva\\"]")'), 'Entrada a reserva '+modo);
        await navigation(() => send('Page.navigate',{url:data.base+'/mis-citas/nueva?'+new URLSearchParams({especialidad_id:data.especialidad,medico_id:data.medico,fecha:data.fecha})}));
        await screen('reserva');
        check(await evaluate('document.querySelector("[data-portal-confirmacion] button").getBoundingClientRect().height>=44'), 'Botón táctil '+modo);
        await navigation(() => evaluate('const f=document.querySelector("[data-portal-confirmacion]");f.querySelector("[name=hora_inicio]").value="08:00";f.querySelector("[name=confirmar]").checked=true;f.requestSubmit();'));
        check(await evaluate('document.body.innerText.includes("La operación se confirmó correctamente.")'), 'Reserva PRG '+modo);
        const ruta = await evaluate('location.pathname');
        await screen('detalle');
        await navigation(() => send('Page.navigate',{url:data.base+ruta+'/reprogramar?fecha='+data.fecha}));
        await screen('reprogramar');
        await navigation(() => evaluate('const f=document.querySelector("[data-portal-confirmacion]");f.querySelector("[name=hora_inicio]").value="08:30";f.querySelector("[name=confirmar]").checked=true;f.requestSubmit();'));
        check(await evaluate('document.body.innerText.includes("08:30")&&document.body.innerText.includes("La operación se confirmó correctamente.")'), 'Nuevo horario '+modo);
        await navigation(() => send('Page.navigate',{url:data.base+ruta+'/cancelar'}));
        await screen('cancelar');
        await navigation(() => evaluate('const f=document.querySelector("[data-portal-confirmacion]");f.querySelector("[name=motivo_cancelacion]").value="CU25_BROWSER_FICTICIO";f.querySelector("[name=confirmar]").checked=true;f.requestSubmit();'));
        check(await evaluate('document.body.innerText.includes("Cancelada")&&!document.querySelector("a[href$=\\"/reprogramar\\"]")'), 'Estado y botones '+modo);
        check(await evaluate('document.body.innerText.includes("Reprogramacion")&&document.body.innerText.includes("Cancelacion")&&!document.body.innerText.includes("CU25_BROWSER_FICTICIO")'), 'Historial sin motivo interno '+modo);
        await screen('historial');
        await logout();await login(data.admin);
        const destino=data.destinos[modoId++];
        await navigation(() => send('Page.navigate',{url:data.base+'/pacientes/'+destino.paciente+'/vincular-cuenta'}));
        await screen('vincular');
        check(await evaluate('document.body.innerText.includes("Coincidir en CI")&&document.body.innerText.includes("Limitación de auditoría")'), 'Advertencia identidad y auditoría '+modo);
        await navigation(() => evaluate(`const f=document.querySelector('[data-portal-vincular]');f.querySelector('[name=cuenta_id]').value=${JSON.stringify(String(destino.usuario))};f.querySelector('[name=identidad_verificada]').checked=true;f.requestSubmit();`));
        check(await evaluate('document.body.innerText.includes("Vinculación realizada por el administrador")&&!document.querySelector("[data-portal-vincular]")'), 'Vinculación inicial PRG '+modo);
        await screen('recibo');
        await logout();await login(data.correo);
    }
    // Edge también notifica recursos data: internos: no son conexiones de red.
    // Cualquier solicitud con otro esquema/origen sigue fallando; las URL se revisan abajo.
    const origenesAjenos = [...new Set(requests.filter(u => new URL(u).protocol !== 'data:' && new URL(u).origin !== new URL(data.base).origin).map(u => new URL(u).origin + ' / ' + new URL(u).protocol))];
    check(origenesAjenos.length === 0, 'Solicitud de navegador fuera del origen local: ' + origenesAjenos.join(', '));
    check(requests.every(u => !u.includes('CU25_BROWSER_')), 'Sin valores clínicos en URL');
    result = { assertions, noCaptures: true };
    await send('Browser.close');
} catch (error) {
    result = { error: error.message, assertions }; process.exitCode = 1;
} finally {
    if (socket?.readyState === WebSocket.OPEN) socket.close();
    if (browser && browser.exitCode === null) { await pause(500); if (browser.exitCode === null) browser.kill(); }
    if (dirname(resolve(profile)) === resolve(tmpdir()) && basename(profile).startsWith('clinica-cu25-browser-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }).catch(() => {});
    }
}
process.stdout.write(JSON.stringify(result));
