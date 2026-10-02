import { spawn } from 'node:child_process';
import { readFile, writeFile, mkdir, rm, realpath } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

let entrada = '';
for await (const trozo of process.stdin) entrada += trozo;
const datos = JSON.parse(entrada);
const raiz = await realpath(process.cwd());
const carpeta = path.resolve(datos.directorio);
assert(carpeta.startsWith(path.join(raiz, 'storage', 'app', 'private', 'pruebas-interfaz') + path.sep));
const perfil = path.join(carpeta, 'perfil-edge');
await mkdir(perfil);
const pasos = [];
const errores = [];
const red = [];
let edge;
let conexion;
let navegador;
const pausa = ms => new Promise(resolver => setTimeout(resolver, ms));

async function esperar(comprobar, descripcion, limite = 15000) {
    const hasta = Date.now() + limite;
    while (Date.now() < hasta) {
        try { if (await comprobar()) return; } catch {}
        await pausa(80);
    }
    throw new Error('No se cumplio: ' + descripcion);
}
async function conectar(url) {
    const socket = new WebSocket(url);
    await new Promise((resolver, rechazar) => {
        socket.addEventListener('open', resolver, { once: true });
        socket.addEventListener('error', () => rechazar(new Error('Conexion de navegador rechazada.')), { once: true });
    });
    let siguiente = 0;
    const pendientes = new Map();
    socket.addEventListener('message', evento => {
        const mensaje = JSON.parse(evento.data);
        if (mensaje.id) {
            const pendiente = pendientes.get(mensaje.id);
            if (!pendiente) return;
            pendientes.delete(mensaje.id);
            clearTimeout(pendiente.temporizador);
            if (mensaje.error) pendiente.rechazar(new Error('Comando de navegador rechazado: ' + pendiente.metodo));
            else pendiente.resolver(mensaje.result);
        } else if (mensaje.method === 'Page.javascriptDialogOpening') {
            enviar('Page.handleJavaScriptDialog', { accept: true }, mensaje.sessionId).catch(() => {});
        } else if (mensaje.method === 'Runtime.exceptionThrown') {
            errores.push(mensaje.params.exceptionDetails.text);
        } else if (mensaje.method === 'Network.responseReceived') {
            if (mensaje.params.response.status >= 400) red.push({ ruta: new URL(mensaje.params.response.url).pathname, estado: mensaje.params.response.status });
        }
    });
    function enviar(metodo, parametros = {}, sesion = undefined) {
        return new Promise((resolver, rechazar) => {
            const id = ++siguiente;
            const temporizador = setTimeout(() => { pendientes.delete(id); rechazar(new Error('Tiempo agotado: ' + metodo)); }, 15000);
            pendientes.set(id, { resolver, rechazar, temporizador, metodo });
            socket.send(JSON.stringify({ id, method: metodo, params: parametros, sessionId: sesion }));
        });
    }
    return { enviar, cerrar: () => socket.close() };
}
async function evaluar(expresion) {
    const respuesta = await conexion.enviar('Runtime.evaluate', { expression: expresion, returnByValue: true, awaitPromise: true });
    if (respuesta.exceptionDetails) throw new Error('No se pudo evaluar una comprobacion de pantalla.');
    return respuesta.result.value;
}
async function ir(ruta) {
    await conexion.enviar('Page.navigate', { url: datos.url + ruta });
    await esperar(() => evaluar(`location.pathname === ${JSON.stringify(ruta)} && document.readyState === 'complete'`), 'abrir ' + ruta);
}
async function pulsar(selector) {
    const cuadro = await evaluar(`(() => {const e=document.querySelector(${JSON.stringify(selector)}); if(!e) throw Error(); e.scrollIntoView({block:'center',behavior:'instant'}); const r=e.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2};})()`);
    await conexion.enviar('Input.dispatchMouseEvent', { type: 'mousePressed', ...cuadro, button: 'left', buttons: 1, clickCount: 1 });
    await conexion.enviar('Input.dispatchMouseEvent', { type: 'mouseReleased', ...cuadro, button: 'left', buttons: 0, clickCount: 1 });
}
async function boton(texto) {
    const selector = await evaluar(`(() => { document.querySelectorAll('[data-prueba-boton]').forEach(e=>delete e.dataset.pruebaBoton); const e=[...document.querySelectorAll('button,a')].find(e=>e.textContent.trim()===${JSON.stringify(texto)}); if(!e) throw Error(); e.dataset.pruebaBoton='actual'; return '[data-prueba-boton="actual"]'; })()`);
    await pulsar(selector);
}
async function rellenar(selector, valor) {
    await evaluar(`(() => {const e=document.querySelector(${JSON.stringify(selector)}); if(!e) throw Error(); e.focus(); e.value=${JSON.stringify(valor)}; e.dispatchEvent(new Event('input',{bubbles:true})); e.dispatchEvent(new Event('change',{bubbles:true}));})()`);
}
async function texto(contenido) {
    await esperar(() => evaluar(`document.body.innerText.includes(${JSON.stringify(contenido)})`), contenido);
}
async function rutaEs(ruta) {
    await esperar(() => evaluar(`location.pathname === ${JSON.stringify(ruta)} && document.readyState === 'complete'`), ruta);
}
async function capturar(nombre) {
    await evaluar('window.scrollTo(0,0)');
    const imagen = await conexion.enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(path.join(carpeta, nombre + '.png'), Buffer.from(imagen.data, 'base64'));
}
async function ancho(valor, alto) {
    await conexion.enviar('Emulation.setDeviceMetricsOverride', { width: valor, height: alto, deviceScaleFactor: 1, mobile: valor < 600 });
}
function paso(descripcion) { pasos.push(descripcion); }

try {
    await esperar(async () => (await fetch(datos.url + '/iniciar-sesion')).ok, 'servidor de pruebas');
    edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
        '--headless=new', '--no-sandbox', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
        '--disable-component-update', '--disable-sync', '--disable-extensions', '--disable-gpu',
        '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', '--user-data-dir=' + perfil,
        'about:blank',
    ], { windowsHide: true, stdio: 'ignore' });
    edge.on('error', () => {});
    let puerto;
    await esperar(async () => {
        const informacion = await readFile(path.join(perfil, 'DevToolsActivePort'), 'utf8');
        puerto = Number(informacion.split('\n')[0]);
        return puerto > 0;
    }, 'iniciar Edge');
    const version = await (await fetch('http://127.0.0.1:' + puerto + '/json/version')).json();
    navegador = await conectar(version.webSocketDebuggerUrl);
    const informacionBrowser = await navegador.enviar('Browser.getVersion');
    process.stdout.write('Navegador conectado: ' + informacionBrowser.product + '\n');
    const pagina = await navegador.enviar('Target.createTarget', {url:'about:blank'});
    const sesion = await navegador.enviar('Target.attachToTarget', {targetId:pagina.targetId, flatten:true});
    conexion = {enviar:(metodo,parametros)=>navegador.enviar(metodo,parametros,sesion.sessionId), cerrar:()=>{}};
    for (const dominio of ['Page', 'Runtime', 'Network']) await conexion.enviar(dominio + '.enable');
    await ancho(1366, 900);
    await ir('/iniciar-sesion');
    await texto('Iniciar sesión');
    assert.equal(await evaluar('document.styleSheets.length > 0'), true);
    await capturar('01-acceso-escritorio');
    await ancho(390, 844);
    assert.equal(await evaluar('document.documentElement.scrollWidth <= innerWidth + 1'), true);
    await capturar('02-acceso-movil');
    await ancho(1366, 900);
    paso('Acceso con estilos y sin desbordamiento horizontal a 390 px.');
    await rellenar('#usuario', datos.usuario);
    await rellenar('#contrasena', 'entrada_incorrecta_de_prueba');
    await boton('Iniciar sesión');
    await texto('No fue posible iniciar sesión');
    paso('Contraseña incorrecta rechazada con mensaje visible.');
    await rellenar('#contrasena', datos.clave);
    await boton('Iniciar sesión');
    await rutaEs('/inicio');
    await texto('Bienvenido, Administrador de prueba');
    assert.equal(await evaluar('document.querySelectorAll("[data-acceso-modulo]").length'), 10);
    await capturar('03-inicio-escritorio');
    await ancho(390, 844);
    assert.equal(await evaluar('document.documentElement.scrollWidth <= innerWidth + 1'), true);
    await capturar('09-inicio-movil');
    await ancho(1366, 900);
    for (const ruta of ['/usuarios', '/roles', '/permisos', '/auditoria']) {
        await pulsar(`nav a[href="${datos.url + ruta}"]`);
        await rutaEs(ruta);
        assert.equal(await evaluar('Boolean(document.querySelector("h1"))'), true);
    }
    paso('Acceso correcto y navegación de usuarios, roles, permisos y auditoría.');
    await ir('/cabanas');
    await boton('Nueva cabaña');
    await rutaEs('/cabanas/crear');
    for (const [campo, valor] of Object.entries({nombre:'Cabaña de prueba visual', capacidad_base:'2', capacidad_maxima:'4', precio_base:'123.45', precio_persona_adicional:'12.30', precio_base_usd:'10.10', precio_persona_adicional_usd:'0.20', estado:'disponible', activa:'1'})) await rellenar('#' + campo, valor);
    await boton('Guardar cabaña');
    await rutaEs('/cabanas');
    await texto('Cabaña de prueba visual');
    await capturar('04-cabanas');
    paso('Cabaña creada desde el formulario con tarifas ficticias HNL y USD independientes.');
    await ir('/huespedes/crear');
    for (const [campo, valor] of Object.entries({nombre:'María Cliente de prueba', telefono:'+504 (001) 234-5678', numero_identidad:'DOC-00123'})) await rellenar('#' + campo, valor);
    await boton('Guardar huésped');
    await esperar(() => evaluar('/^\\/huespedes\\/\\d+$/.test(location.pathname) && document.readyState === "complete"'), 'ficha de huésped');
    await boton('Desactivar');
    await texto('Inactivo');
    await boton('Incluir en lista negra');
    await texto('Lista negra');
    paso('Huésped creado y marcado inactivo/lista negra mediante botones con confirmación.');
    await ir('/reservaciones/crear');
    for (const [campo, valor] of Object.entries({cabana_id:'1', fecha_entrada:'2026-10-05', fecha_salida:'2026-10-07', cantidad_huespedes:'3', moneda:'HNL', notas:'Notas ficticias conservadas'})) await rellenar('#' + campo, valor);
    for (const termino of ['maria', '5040012345678', 'DOC00123']) {
        await rellenar('[data-entrada]', termino);
        await pulsar('[data-buscar]');
        await esperar(() => evaluar('document.querySelectorAll("[data-resultados] button").length === 1'), 'resultado de búsqueda');
        assert.equal(await evaluar('document.querySelector("[data-identificador]").value'), '');
        await conexion.enviar('Runtime.evaluate', { expression: 'document.querySelector("[data-entrada]").focus()' });
        for (const tecla of ['ArrowDown', 'Enter']) {
            await conexion.enviar('Input.dispatchKeyEvent', { type:'keyDown', key:tecla, code:tecla });
            await conexion.enviar('Input.dispatchKeyEvent', { type:'keyUp', key:tecla, code:tecla });
        }
        await esperar(() => evaluar('document.querySelector("[data-identificador]").value === "1"'), 'selección explícita con teclado');
        assert.equal(await evaluar('document.querySelector("#notas").value'), 'Notas ficticias conservadas');
        assert.equal(await evaluar('document.querySelector("#moneda").value'), 'HNL');
    }
    await texto('Advertencia: inactivo');
    await texto('Advertencia: lista negra');
    await capturar('05-buscador-reservacion');
    paso('Búsqueda real por nombre sin tildes, teléfono sin separadores e identidad; selección con teclado y conservación de campos/advertencias.');
    await boton('Guardar reservación');
    await rutaEs('/reservaciones/1');
    await texto('HNL 271.50');
    await texto('Por confirmar');
    await boton('Reagendar o editar');
    await rutaEs('/reservaciones/1/editar');
    await rellenar('#fecha_entrada', '2026-10-08');
    await rellenar('#fecha_salida', '2026-10-10');
    await boton('Guardar reprogramación');
    await rutaEs('/reservaciones/1');
    await texto('08/10/2026 14:00');
    await texto('HNL 271.50');
    await boton('Confirmar');
    await texto('Confirmada');
    await capturar('06-reservacion-confirmada');
    paso('Reservación creada, reagendada conservando tarifa y confirmada; importe mostrado HNL 271.50.');
    await boton('Cancelar reservación');
    await texto('Cancelada');
    await capturar('07-reservacion-cancelada');
    await ir('/reservaciones');
    await ancho(390, 844);
    assert.equal(await evaluar('document.documentElement.scrollWidth <= innerWidth + 1'), true);
    await capturar('08-reservaciones-movil');
    paso('Cancelación desde la ficha y listado móvil sin desbordamiento de la página.');
    await ancho(1366, 900);
    await ir('/inicio');
    await pulsar(`[data-acceso-modulo][href="${datos.url + '/reservaciones/crear'}"]`);
    await rutaEs('/reservaciones/crear');
    for (const [campo, valor] of Object.entries({cabana_id:'1', fecha_entrada:'2026-10-12', fecha_salida:'2026-10-13', cantidad_huespedes:'3', moneda:'HNL', notas:'Conservar durante alta rápida'})) await rellenar('#' + campo, valor);
    await boton('Nuevo huésped');
    await pulsar('[data-guardar-alta]');
    assert.equal(await evaluar('document.querySelector("[data-identificador]").value'), '');
    await rellenar('[data-alta-nombre]', 'Mario Huésped rápido');
    await rellenar('[data-alta-telefono]', '+44 (020) 000-1234');
    await ancho(390, 844);
    assert.equal(await evaluar('document.documentElement.scrollWidth <= innerWidth + 1'), true);
    await capturar('10-alta-huesped-movil');
    await ancho(1366, 900);
    await boton('Crear y seleccionar huésped');
    await esperar(() => evaluar('document.querySelector("[data-identificador]").value === "2"'), 'huésped creado desde reservación');
    assert.equal(await evaluar('document.querySelector("[data-alta-huesped]").hidden'), true);
    assert.equal(await evaluar('document.querySelector("#notas").value'), 'Conservar durante alta rápida');
    assert.equal(await evaluar('document.querySelector("#fecha_entrada").value'), '2026-10-12');
    await texto('Identidad: Sin registrar');
    await rellenar('[data-entrada]', 'Mario');
    await pulsar('[data-buscar]');
    await esperar(() => evaluar('document.querySelectorAll("[data-resultados] button").length === 1'), 'encontrar huésped recién creado');
    await pulsar('[data-resultados] button');
    await boton('Guardar reservación');
    await rutaEs('/reservaciones/2');
    await texto('Mario Huésped rápido');
    await texto('HNL 135.75');
    await capturar('11-reservacion-alta-rapida');
    paso('Tarjetas de inicio en escritorio/móvil y alta de huésped sin identidad desde reservación, campos conservados y búsqueda por botón reutilizando el nuevo registro.');
    await boton('Cerrar sesión');
    await rutaEs('/iniciar-sesion');
    await conexion.enviar('Page.navigate', { url: datos.url + '/usuarios' });
    await rutaEs('/iniciar-sesion');
    paso('Cierre de sesión y redirección al acceso al intentar volver a usuarios.');
    assert.deepEqual(errores, []);
    assert.deepEqual(red.filter(respuesta => respuesta.ruta !== '/favicon.ico'), []);
    paso('Sin excepciones JavaScript ni errores HTTP de pantallas/recursos en este recorrido.');
    await writeFile(path.join(carpeta, 'resultado.json'), JSON.stringify({resultado:'correcto', navegador:version.Browser, pasos, errores, respuestas_error:red}, null, 2));
    process.stdout.write(JSON.stringify({resultado:'correcto', navegador:version.Browser, pasos:pasos.length, evidencias:carpeta}, null, 2) + '\n');
} catch (error) {
    try { if (conexion) await capturar('fallo'); } catch {}
    await writeFile(path.join(carpeta, 'resultado.json'), JSON.stringify({resultado:'fallido', pasos, error:error.message, errores, respuestas_error:red}, null, 2));
    process.stdout.write(JSON.stringify({resultado:'fallido', pasos:pasos.length, error:error.message, evidencias:carpeta}, null, 2) + '\n');
    process.exitCode = 1;
} finally {
    conexion?.cerrar();
    try { await navegador?.enviar('Browser.close'); } catch {}
    navegador?.cerrar();
    if (edge && edge.exitCode === null) {
        await Promise.race([new Promise(resolver => edge.once('exit', resolver)), pausa(2000)]);
        if (edge.exitCode === null) edge.kill();
    }
    const perfilReal = await realpath(perfil);
    const carpetaReal = await realpath(carpeta);
    assert.equal(perfilReal, path.join(carpetaReal, 'perfil-edge'));
    assert(carpetaReal.startsWith(path.join(raiz, 'storage', 'app', 'private', 'pruebas-interfaz') + path.sep));
    await rm(perfilReal, {recursive:true, force:true, maxRetries:8, retryDelay:250});
}
