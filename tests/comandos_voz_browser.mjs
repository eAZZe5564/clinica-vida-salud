import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';
import { join, dirname, basename, resolve } from 'node:path';

// Metadatos ficticios por stdin; no colocar credenciales en argumentos ni logs.
let input = ''; for await (const chunk of process.stdin) input += chunk;
const data = JSON.parse(input);
const profile = await mkdtemp(join(tmpdir(), 'clinica-cu26-browser-'));
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
    await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
    const requests=[], posts=[], abortos=[];
    listeners.set('Network.requestWillBeSent',new Set([e=>{requests.push(e.request.url);if(e.request.method!=='GET')posts.push(new URL(e.request.url).pathname);} ]));
    await send('Runtime.addBinding',{name:'__vozTestNotice'});
    listeners.set('Runtime.bindingCalled',new Set([e=>{if(e.name==='__vozTestNotice')abortos.push(e.payload);} ]));
    const login = async rol => {
        await navigation(()=>send('Page.navigate',{url:data.base+'/login'}));
        await navigation(()=>evaluate(`document.querySelector('[name=correo]').value=${JSON.stringify(data.actores[rol].correo)};document.querySelector('[name=contrasena]').value=${JSON.stringify(data.password)};document.querySelector('form[action="/login"]').requestSubmit();`));
        check(await evaluate('location.pathname==="/dashboard"'),'Login ficticio '+rol);
    };
    const logout=async()=>navigation(()=>evaluate('document.querySelector("form[action=\\"/logout\\"]").requestSubmit()'));
    await login('paciente');
    const real=await evaluate(`({standard:typeof window.SpeechRecognition==='function',webkit:typeof window.webkitSpeechRecognition==='function',secureContext:isSecureContext,microphoneStarted:false})`);
    real.navegador=(await send('Browser.getVersion')).product;
    // Solo detectar disponibilidad real. No invocar start() del micrófono nativo.
    let scriptId;
    const modo=async tipo=>{
        if(scriptId)await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:scriptId});
        const fuente=`(()=>{
            const tipo=${JSON.stringify(tipo)};
            window.__voz={creadas:0,abortadas:0,last:null};
            class Simulada{
                constructor(){if(tipo==='constructor-error')throw new Error('Fallo ficticio');window.__voz.creadas++;window.__voz.last=this;}
                start(){if(tipo==='start-error')throw new Error('Fallo ficticio');queueMicrotask(()=>{if(tipo==='denegado')this.onerror?.({error:'not-allowed'});else this.onstart?.();});}
                abort(){window.__voz.abortadas++;window.__vozTestNotice?.('abort');this.onend?.();}
            }
            window.__vozAPISimulada=Simulada;
            Object.defineProperty(window,'SpeechRecognition',{configurable:true,value:tipo==='webkit'||tipo==='sin-api'?undefined:Simulada});
            Object.defineProperty(window,'webkitSpeechRecognition',{configurable:true,value:tipo==='webkit'?Simulada:undefined});
            if(tipo==='sin-contexto')Object.defineProperty(window,'isSecureContext',{configurable:true,value:false});
            if(tipo==='timeout'){const original=window.setTimeout;window.setTimeout=(fn,ms,...args)=>original(fn,ms===12000?100:ms,...args);}
            window.__vozEmit=t=>window.__voz.last?.onresult?.({resultIndex:0,results:[{isFinal:true,0:{transcript:t}}]});
        })();`;
        scriptId=(await send('Page.addScriptToEvaluateOnNewDocument',{source:fuente})).identifier;
        await navigation(()=>send('Page.navigate',{url:data.base+'/dashboard'}));
        if(!await evaluate(tipo==='sin-api'
            ? 'typeof window.SpeechRecognition==="undefined"&&typeof window.webkitSpeechRecognition==="undefined"'
            : tipo==='webkit' ? 'window.webkitSpeechRecognition===window.__vozAPISimulada&&typeof window.SpeechRecognition==="undefined"'
            : 'window.SpeechRecognition===window.__vozAPISimulada')) throw new Error('La prueba no puede activar una API nativa');
        await evaluate('document.querySelector("[data-voz]").open=true');await pause(30);
    };
    const iniciar=async()=>{await evaluate('document.querySelector("[data-voz-consentimiento]").checked=true;document.querySelector("[data-voz-consentimiento]").dispatchEvent(new Event("change"));document.querySelector("[data-voz-iniciar]").click()');await pause(30);};
    const manual=async t=>navigation(()=>evaluate(`const f=document.querySelector('[data-voz-manual]');f.querySelector('[data-voz-texto]').value=${JSON.stringify(t)};f.requestSubmit();`));
    await modo('standard');
    check(await evaluate('window.__voz.creadas===0&&document.querySelector("[data-voz-iniciar]").disabled'),'Sin inicio automático ni consentimiento implícito');
    await evaluate('document.querySelector("[data-voz-iniciar]").click()');
    check(await evaluate('window.__voz.creadas===0'),'Botón sin consentimiento no captura');
    await iniciar();
    check(await evaluate('window.__voz.last.lang==="es-BO"&&!window.__voz.last.continuous&&!window.__voz.last.interimResults&&window.__voz.last.maxAlternatives===1'),'Configuración española de sesión única');
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("Escuchando")&&!document.querySelector("[data-voz-detener]").disabled'),'Indicador textual y detención');
    await evaluate('window.__vozEmit("ABRIR AYUDA")');
    check(await evaluate('document.activeElement.matches("[data-voz-ayuda]")&&window.__voz.abortadas===1'),'Ayuda, foco y micrófono abortado tras resultado');
    await iniciar();await evaluate('window.__vozEmit("<img src=x onerror=window.CU26_XSS=1>")');
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("no reconocido")&&!document.querySelector("[data-voz-reconocido] img")&&!window.CU26_XSS'),'Transcripción temporal escapada y desconocida');
    await iniciar();await evaluate('document.querySelector("[data-voz-detener]").click();window.__vozEmit("abrir mis citas")');
    check(await evaluate('location.pathname==="/dashboard"&&document.querySelector("[data-voz-reconocido]").textContent===""'),'Detener descarta eventos tardíos');
    await iniciar();await evaluate('document.querySelector("[data-voz]").open=false;window.__vozEmit("abrir mis citas")');await pause(30);
    check(await evaluate('location.pathname==="/dashboard"&&!document.querySelector("[data-voz-consentimiento]").checked'),'Cerrar panel cancela y revoca consentimiento');
    await modo('denegado');await iniciar();
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("denegado")&&document.querySelector("[data-voz-detener]").disabled'),'Permiso denegado manejado');
    await manual('abrir mis citas');check(await evaluate('location.pathname==="/mis-citas"'),'Alternativa manual tras denegación');
    for(const tipo of ['sin-api','sin-contexto','constructor-error','start-error']) {
        await modo(tipo);
        if(tipo==='sin-api'||tipo==='sin-contexto')check(await evaluate('document.querySelector("[data-voz-iniciar]").disabled'),'Incompatibilidad detectada '+tipo);
        else {await iniciar();check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("No se pudo iniciar")'),'Error de inicio manejado '+tipo);}
        await manual('consultar mi historial');check(await evaluate('location.pathname==="/mis-citas/historial"'),'Texto funcional '+tipo);
    }
    await modo('sin-api');
    await navigation(()=>evaluate('document.querySelector("[data-voz-seleccion]").value="reservar";document.querySelector("[data-voz-manual]").requestSubmit()'));
    check(await evaluate('location.pathname==="/mis-citas/nueva"&&!!document.querySelector("[data-portal-disponibilidad]")'),'Selector manual solo abre el formulario de reserva');
    await modo('standard');
    await evaluate('document.querySelector("[data-voz-seleccion]").value="reservar";document.querySelector("[data-voz-texto]").value="/pacientes/1/alergias";document.querySelector("[data-voz-manual]").requestSubmit()');
    check(await evaluate('location.pathname==="/dashboard"&&document.querySelector("[data-voz-estado]").textContent.includes("no reconocido")'),'Entrada arbitraria no cae en otra opción del selector');
    await iniciar();await evaluate('window.__voz.last.onerror({error:"no-speech"})');
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("No se detectó")&&window.__voz.creadas===1'),'Silencio sin reinicio automático');
    await iniciar();await evaluate('window.dispatchEvent(new PageTransitionEvent("pagehide"));window.__vozEmit("abrir mis citas")');
    check(await evaluate('location.pathname==="/dashboard"&&document.querySelector("[data-voz-reconocido]").textContent===""&&!document.querySelector("[data-voz-consentimiento]").checked'),'Salida de página limpia texto y consentimiento');
    await modo('webkit');await iniciar();await evaluate('window.__voz.last.onerror({error:"language-not-supported"})');
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("idioma no está disponible")&&window.__voz.creadas===1'),'Idioma no soportado no reinicia voz');
    await evaluate('document.querySelector("[data-voz-idioma]").value="es-ES"');await iniciar();
    check(await evaluate('window.__voz.last.lang==="es-ES"'),'Alternativa es-ES explícita mediante prefijo webkit');
    await evaluate('window.__voz.last.onerror({error:"network",message:"CU26_TEXTO_NO_DIVULGAR"})');
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("alternativa manual")&&!document.body.textContent.includes("CU26_TEXTO_NO_DIVULGAR")'),'Error nativo no se refleja');
    await modo('timeout');await iniciar();await pause(180);
    check(await evaluate('document.querySelector("[data-voz-estado]").textContent.includes("agotado")&&window.__voz.abortadas===1'),'Límite de sesión con reloj acelerado de prueba');
    await modo('standard');await iniciar();
    await evaluate('Object.defineProperty(document,"hidden",{configurable:true,value:true});document.dispatchEvent(new Event("visibilitychange"));window.__vozEmit("abrir mis citas")');
    check(await evaluate('document.querySelector("[data-voz-reconocido]").textContent===""&&window.__voz.abortadas===1'),'Pestaña oculta descarta voz');
    for(const [modoPantalla,width,height] of [['escritorio',1440,1000],['tablet',820,1180],['celular',390,844]]){
        await modo('standard');await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
        check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'Sin desbordamiento '+modoPantalla);
        check(await evaluate('document.querySelector("[data-voz-iniciar]").getBoundingClientRect().height>=44'),'Control táctil '+modoPantalla);
        check(await evaluate('!!document.querySelector("[data-voz-estado][role=status][aria-live=polite]")'),'Estado accesible '+modoPantalla);
        // Enter en el campo real, sin enviar un formulario a PHP.
        await evaluate('document.querySelector("[data-voz-texto]").focus();document.querySelector("[data-voz-texto]").value="Abrir ayuda"');
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',text:'\r',unmodifiedText:'\r',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await pause(50);
        check(await evaluate('document.activeElement.matches("[data-voz-ayuda]")'),'Alternativa por teclado '+modoPantalla);
        await iniciar();await navigation(()=>evaluate('window.__vozEmit("Ver MÍS PRÓXIMAS CÍTAS")'));
        check(await evaluate('location.pathname==="/mis-citas"&&location.search==="?periodo=proximas"'),'Voz simulada abre próximas propias '+modoPantalla);
    }
    for(const [rol,frase,ruta] of [['medico','ver la agenda de hoy','/agenda/diaria'],['administrador','abrir reportes','/reportes/citas'],['recepcionista','buscar pacientes','/pacientes'],['sin_vinculo','ir al inicio','/dashboard']]){
        await logout();await login(rol);await modo('standard');
        if(rol==='sin_vinculo')check(await evaluate('!document.querySelector("[data-voz-seleccion] option[value=mis_citas]")&&document.body.textContent.includes("vinculación activa verificada")'),'Paciente no vinculado sin catálogo de citas');
        await iniciar();await navigation(()=>evaluate(`window.__vozEmit(${JSON.stringify(frase)})`));
        check(await evaluate(`location.pathname===${JSON.stringify(ruta)}`),'Destino real por rol '+rol);
    }
    await modo('standard');await iniciar();const antesLogout=abortos.length;await logout();
    check(abortos.length>antesLogout,'Logout habitual abortó el reconocimiento antes de salir');
    check(await evaluate('location.pathname==="/login"&&!document.querySelector("[data-voz]")'),'Sin panel ni captura tras cerrar sesión');
    await navigation(()=>send('Page.navigate',{url:data.base+'/mis-citas'}));check(await evaluate('location.pathname==="/login"'),'Servidor vuelve a exigir autenticación');
    check(requests.every(u=>new URL(u).protocol==='data:'||new URL(u).origin===new URL(data.base).origin),'Sin conexiones de aplicación a terceros en pruebas');
    check(requests.every(u=>!u.includes('CU26_')&&!u.includes('transcrip')),'Sin transcripciones en URL');
    check(posts.every(p=>p==='/login'||p==='/logout'),'No se enviaron transcripciones ni escrituras por comandos');
    result={assertions,real,microfonoRealProbado:false,sinCapturas:true};
    await send('Browser.close');
} catch (error) {
    result = { error: error.message, assertions }; process.exitCode = 1;
} finally {
    if (socket?.readyState === WebSocket.OPEN) socket.close();
    if (browser && browser.exitCode === null) { await pause(500); if (browser.exitCode === null) browser.kill(); }
    if (dirname(resolve(profile)) === resolve(tmpdir()) && basename(profile).startsWith('clinica-cu26-browser-')) {
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }).catch(() => {});
    }
}
process.stdout.write(JSON.stringify(result));
