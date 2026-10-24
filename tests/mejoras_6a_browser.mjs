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
    const directory = resolve('tests/artifacts/mejoras-6a'); await mkdir(directory, { recursive: true });
    const captures = [];
    const screenshot = async (name) => { const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }); const path = join(directory, name); await writeFile(path, Buffer.from(shot.data, 'base64')); captures.push(path); };
    await navigation(() => send('Page.navigate', { url: `${data.base}/pacientes?q=${encodeURIComponent(`María Pérez ${data.sufijo}`)}` }));
    check(await evaluate('document.querySelectorAll("tbody tr").length===1&&document.body.innerText.includes("María Elena")'), 'Búsqueda real entre nombre y apellido');
    await navigation(() => evaluate(`document.querySelector('[name=q]').value=${JSON.stringify(`1234567 Pérez ${data.sufijo} María`)};document.querySelector('.search-form').requestSubmit();`));
    check(await evaluate('document.querySelectorAll("tbody tr").length===1&&new URLSearchParams(location.search).get("q").startsWith("1234567 Pérez")'), 'Formulario busca CI, apellido y nombre en otro orden');
    check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Búsqueda escritorio sin desbordamiento');
    await screenshot('busqueda-escritorio.png');
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Búsqueda móvil sin desbordamiento');
    check(await evaluate('getComputedStyle(document.querySelector("td")).display==="flex"'), 'Resultado móvil con etiquetas legibles');
    await screenshot('busqueda-movil.png');
    await navigation(() => send('Page.navigate', { url: `${data.base}/admin/users/new` }));
    check(await evaluate('document.querySelector("#password-help").innerText.includes("8 caracteres")&&document.querySelector("#password-help").innerText.includes("72 bytes")'), 'Ayuda de contraseña completa');
    await navigation(() => evaluate(`document.querySelector('[name=correo]').value=${JSON.stringify(data.nuevoCorreo)};document.querySelector('[name=rol_id]').value=${JSON.stringify(String(data.rol))};document.querySelector('[name=contrasena]').value='abcdef1!';document.querySelector('.user-form').requestSubmit();`));
    check(await evaluate('document.querySelector("#password-errors")?.innerText.includes("mayúscula")'), 'Servidor devuelve requisito de contraseña faltante');
    check(await evaluate('document.querySelector("[name=contrasena]").value===""'), 'El error nunca repuebla la contraseña');
    check(await evaluate(`document.querySelector('[name=correo]').value===${JSON.stringify(data.nuevoCorreo)}&&document.querySelector('[name=rol_id]').value===${JSON.stringify(String(data.rol))}`), 'Conserva correo y rol al corregir');
    check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Error de contraseña móvil sin desbordamiento');
    await screenshot('contrasena-movil.png');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
    check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'Error de contraseña escritorio sin desbordamiento');
    await screenshot('contrasena-escritorio.png');
    await navigation(() => evaluate(`document.querySelector('[name=contrasena]').value=${JSON.stringify(data.nuevaClave)};document.querySelector('.user-form').requestSubmit();`));
    check(await evaluate('location.pathname==="/admin/users"&&document.body.innerText.includes("La cuenta se creó correctamente")'), 'Crea cuenta válida desde navegador');
    result = { assertions, captures };
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
