import test from 'node:test';
import assert from 'node:assert/strict';
import { BuscadorHuesped, caracteresUtiles } from '../../resources/js/buscador-huesped.js';

// Sustituto mínimo del contrato DOM; no equivale a una revisión en navegador.
class Elemento extends EventTarget {
    constructor() {
        super();
        this.value = '';
        this.textContent = '';
        this.hidden = false;
        this.children = [];
        this.atributos = new Map();
        this.classList = { toggle() {} };
    }
    setAttribute(nombre, valor) { this.atributos.set(nombre, valor); }
    removeAttribute(nombre) { this.atributos.delete(nombre); }
    replaceChildren(...hijos) { this.children = hijos; }
    focus() { this.enfocado = true; }
    setCustomValidity(valor) { this.validacion = valor; }
    reportValidity() { return this.valido !== false; }
}

function preparar(solicitar = async () => respuesta([]), { alta = false } = {}) {
    const raiz = new Elemento();
    raiz.id = 'cliente-prueba';
    raiz.dataset = { url: '/huespedes/buscar', requerido: '1' };
    const elementos = Object.fromEntries(['entrada', 'identificador', 'estado', 'resultados', 'seleccion', 'cambiar', 'reintentar', 'crear', 'buscar'].map(nombre => [nombre, new Elemento()]));
    if (alta) {
        const panel = new Elemento();
        panel.hidden = true;
        panel.dataset = { url: '/huespedes/alta-en-formulario' };
        elementos['alta-huesped'] = panel;
        for (const nombre of ['abrir-alta', 'guardar-alta', 'cerrar-alta', 'alta-estado', 'alta-nombre', 'alta-telefono', 'alta-identidad']) elementos[nombre] = new Elemento();
        panel.querySelector = selector => elementos[selector.slice(6, -1)];
    }
    raiz.querySelector = selector => elementos[selector.slice(6, -1)];
    raiz.ownerDocument = { createElement: () => new Elemento(), querySelector: () => ({content:'token_csrf_prueba'}) };
    const formulario = new Elemento();
    formulario.fecha = '2026-12-20';
    raiz.closest = () => formulario;
    const buscador = new BuscadorHuesped(raiz, { solicitar, demora: 10000 });
    return { raiz, elementos, formulario, buscador };
}
function respuesta(resultados, hay_mas = false) {
    return { ok: true, json: async () => ({ resultados, hay_mas }) };
}
function cliente(id, nombre = 'Ana López') {
    return { id, nombre, telefono: '001-234', numero_identidad: '00-123', identidad_visible: true, activo: true, lista_negra: false };
}
function tecla(entrada, key) {
    const evento = new Event('keydown', { cancelable: true });
    evento.key = key;
    entrada.dispatchEvent(evento);
    return evento;
}
function diferida() {
    let resolver;
    const promesa = new Promise(resolve => { resolver = resolve; });
    return { promesa, resolver };
}

test('no consulta con menos de tres caracteres útiles, incluidos separadores', async () => {
    let consultas = 0;
    const { buscador, elementos } = preparar(async () => { consultas++; return respuesta([]); });
    for (const texto of ['', '  -- ', 'a-2', '%_!', 'á']) {
        elementos.entrada.value = texto;
        await buscador.consultar();
    }
    assert.equal(consultas, 0);
    assert.equal(caracteresUtiles('á 0-ñ'), 3);
    assert.match(elementos.estado.textContent, /tres/);
});

test('una sola coincidencia exige selección explícita y permite teclado', async () => {
    const { buscador, elementos } = preparar(async () => respuesta([cliente(42)]));
    elementos.entrada.value = 'Ana';
    await buscador.consultar();
    assert.equal(elementos.identificador.value, '');
    assert.equal(tecla(elementos.entrada, 'Enter').defaultPrevented, true);
    assert.equal(elementos.identificador.value, '');
    tecla(elementos.entrada, 'ArrowDown');
    assert.equal(elementos.identificador.value, '');
    tecla(elementos.entrada, 'Enter');
    assert.equal(elementos.identificador.value, '42');
    assert.equal(elementos.resultados.hidden, true);
    assert.match(elementos.seleccion.textContent, /Ana López/);
});

test('clic y cambio de cliente conservan los demás campos del formulario', async () => {
    const { buscador, elementos, formulario } = preparar(async () => respuesta([cliente(7), cliente(8)]));
    elementos.entrada.value = 'Ana';
    await buscador.consultar();
    elementos.resultados.children[1].dispatchEvent(new Event('click'));
    assert.equal(elementos.identificador.value, '8');
    elementos.entrada.value = 'Beatriz';
    elementos.entrada.dispatchEvent(new Event('input'));
    assert.equal(elementos.identificador.value, '');
    assert.equal(elementos.seleccion.textContent, '');
    assert.equal(formulario.fecha, '2026-12-20');
    buscador.cancelar();
});

test('una respuesta antigua no sustituye resultados recientes ni una selección', async () => {
    const antigua = diferida();
    const reciente = diferida();
    let llamadas = 0;
    const { buscador, elementos } = preparar(() => ++llamadas === 1 ? antigua.promesa : reciente.promesa);
    elementos.entrada.value = 'Ana';
    const primera = buscador.consultar();
    elementos.entrada.value = 'Beatriz';
    buscador.editar();
    const segunda = buscador.consultar();
    reciente.resolver(respuesta([cliente(2, 'Beatriz')]));
    await segunda;
    elementos.resultados.children[0].dispatchEvent(new Event('click'));
    antigua.resolver(respuesta([cliente(1, 'Ana')]));
    await primera;
    assert.equal(elementos.identificador.value, '2');
    assert.match(elementos.seleccion.textContent, /Beatriz/);
    assert.equal(elementos.resultados.children.length, 0);
});

test('descarta respuestas al acortar el texto o cerrar con Escape', async () => {
    const pendiente = diferida();
    const { buscador, elementos } = preparar(() => pendiente.promesa);
    elementos.entrada.value = 'Ana';
    const consulta = buscador.consultar();
    elementos.entrada.value = 'An';
    buscador.editar();
    tecla(elementos.entrada, 'Escape');
    pendiente.resolver(respuesta([cliente(1)]));
    await consulta;
    assert.equal(elementos.resultados.children.length, 0);
    assert.equal(elementos.identificador.value, '');
});

test('distingue conexión fallida de ausencia de resultados y permite reintentar', async () => {
    let falla = true;
    const { buscador, elementos } = preparar(async () => {
        if (falla) throw new Error('Sin conexión');
        return respuesta([]);
    });
    elementos.entrada.value = 'Ana';
    await buscador.consultar();
    assert.match(elementos.estado.textContent, /No se pudo consultar/);
    assert.equal(elementos.crear.hidden, true);
    assert.equal(elementos.reintentar.hidden, false);
    falla = false;
    await buscador.consultar();
    assert.equal(elementos.estado.textContent, 'No se encontraron clientes');
    assert.equal(elementos.crear.hidden, false);
    assert.equal(elementos.reintentar.hidden, true);
});

test('permite seleccionar inactivos y lista negra mostrando advertencias y ocultando identidad restringida', async () => {
    const { buscador, elementos } = preparar(async () => respuesta([{ ...cliente(3), activo: false, lista_negra: true, identidad_visible: false, numero_identidad: null }], true));
    elementos.entrada.value = 'Ana';
    await buscador.consultar();
    assert.match(elementos.estado.textContent, /refina/);
    assert.match(elementos.resultados.children[0].textContent, /Restringida.*inactivo.*lista negra/);
    tecla(elementos.entrada, 'ArrowUp');
    tecla(elementos.entrada, 'Enter');
    assert.equal(elementos.identificador.value, '3');
    assert.match(elementos.seleccion.textContent, /Advertencia/);
});

test('rechaza respuesta HTTP de acceso denegado sin mostrar ausencia de clientes', async () => {
    const { buscador, elementos } = preparar(async () => ({ ok: false, status: 403 }));
    elementos.entrada.value = 'Ana';
    await buscador.consultar();
    assert.equal(elementos.reintentar.hidden, false);
    assert.doesNotMatch(elementos.estado.textContent, /No se encontraron/);
});

test('impide enviar un formulario requerido sin seleccionar aunque haya texto', () => {
    const { formulario, elementos } = preparar();
    elementos.entrada.value = 'Texto sin selección';
    const evento = new Event('submit', { cancelable: true });
    formulario.dispatchEvent(evento);
    assert.equal(evento.defaultPrevented, true);
    assert.match(elementos.entrada.validacion, /Selecciona/);
});

test('escapa el término en URL y solicita JSON sin caché con sesión propia', async () => {
    let parametros;
    const { buscador, elementos } = preparar(async (...valores) => { parametros = valores; return respuesta([]); });
    elementos.entrada.value = 'Ana & 00+123';
    await buscador.consultar();
    assert.equal(new URL(parametros[0], 'https://ejemplo.test').searchParams.get('termino_busqueda'), 'Ana & 00+123');
    assert.equal(parametros[1].cache, 'no-store');
    assert.equal(parametros[1].credentials, 'same-origin');
    assert.equal(parametros[1].headers.Accept, 'application/json');
});

test('el alta crea y selecciona con CSRF conservando la reservacion y evita doble envio', async () => {
    const pendiente = diferida();
    const solicitudes = [];
    const { buscador, elementos, formulario } = preparar((...argumentos) => { solicitudes.push(argumentos); return pendiente.promesa; }, { alta:true });
    elementos['abrir-alta'].dispatchEvent(new Event('click'));
    elementos['alta-nombre'].value = 'Cliente rápido';
    elementos['alta-telefono'].value = '+504 00123';
    const primera = buscador.crearHuesped();
    await buscador.crearHuesped();
    assert.equal(solicitudes.length, 1);
    assert.equal(elementos['guardar-alta'].disabled, true);
    assert.equal(solicitudes[0][1].headers['X-CSRF-TOKEN'], 'token_csrf_prueba');
    assert.deepEqual(JSON.parse(solicitudes[0][1].body), {nombre:'Cliente rápido', telefono:'+504 00123', numero_identidad:null});
    pendiente.resolver({ok:true, status:201, json:async () => ({huesped:cliente(99, 'Cliente rápido')})});
    await primera;
    assert.equal(elementos.identificador.value, '99');
    assert.equal(formulario.fecha, '2026-12-20');
    assert.equal(elementos['alta-huesped'].hidden, true);
    assert.equal(elementos['alta-nombre'].disabled, true);
    assert.equal(elementos['guardar-alta'].disabled, false);
});

test('el alta mantiene los datos ante validacion fallida y no selecciona ni modifica la reservacion', async () => {
    const { buscador, elementos, formulario } = preparar(async () => ({ok:false, status:422, json:async () => ({errors:{numero_identidad:['La identidad ya está registrada.']}})}), { alta:true });
    elementos['abrir-alta'].dispatchEvent(new Event('click'));
    elementos['alta-nombre'].value = 'Nombre conservado';
    elementos['alta-telefono'].value = '+1 00123';
    elementos['alta-identidad'].value = 'DOC001';
    await buscador.crearHuesped();
    assert.equal(elementos.identificador.value, '');
    assert.equal(elementos['alta-nombre'].value, 'Nombre conservado');
    assert.equal(elementos['alta-huesped'].hidden, false);
    assert.equal(elementos['alta-estado'].textContent, 'La identidad ya está registrada.');
    assert.equal(formulario.fecha, '2026-12-20');
    elementos['cerrar-alta'].dispatchEvent(new Event('click'));
    assert.equal(elementos['alta-nombre'].disabled, true);
});

test('el alta no envia campos invalidos y ofrece buscar antes de reintentar un error de conexion', async () => {
    let consultas = 0;
    const { buscador, elementos } = preparar(async () => { consultas++; throw Error('Sin conexión'); }, { alta:true });
    elementos['abrir-alta'].dispatchEvent(new Event('click'));
    elementos['alta-nombre'].valido = false;
    await buscador.crearHuesped();
    assert.equal(consultas, 0);
    elementos['alta-nombre'].valido = true;
    await buscador.crearHuesped();
    assert.equal(consultas, 1);
    assert.match(elementos['alta-estado'].textContent, /busca el huésped antes de reintentar/);
    assert.equal(elementos.identificador.value, '');
    assert.equal(elementos['guardar-alta'].disabled, false);
});
