// Sincroniza cantidad, tipos de cama y resumen en cada selector del formulario.
export function iniciarSelectoresCamas() {
    document.querySelectorAll('[data-selector-camas]').forEach((selector) => {
        const numero = selector.querySelector('[data-numero-camas]');
        const lista = selector.querySelector('[data-lista-camas]');
        const plantilla = selector.querySelector('[data-plantilla-cama]');
        const total = selector.querySelector('[data-total-camas]');
        const estado = selector.querySelector('[data-estado-camas]');
        const selecciones = new Map();

        // Conserva los tipos por posición para recuperarlos al reducir y ampliar la cantidad.
        const recordarSelecciones = () => {
            lista.querySelectorAll('[data-tipo-cama]').forEach((campo, indice) => selecciones.set(indice, campo.value));
        };
        // Cuenta las camas configuradas y distingue un dato sin registrar de una cantidad explícita.
        const actualizarResumen = () => {
            let configuradas = 0;
            lista.querySelectorAll('[data-cama]').forEach((cama, indice) => {
                const campo = cama.querySelector('[data-tipo-cama]');
                if (campo.value) configuradas++;
                cama.querySelector('[data-resumen-cama]').textContent = `Cama ${indice + 1} · ${campo.value ? campo.selectedOptions[0].textContent : 'Selecciona el tipo'}`;
            });
            estado.textContent = numero.value === '' ? 'Sin registrar' : `${configuradas} de ${lista.children.length} camas con tipo seleccionado.`;
        };
        // Valida la cantidad, ajusta las filas desde la plantilla y restaura las selecciones existentes.
        const ajustarCantidad = () => {
            recordarSelecciones();
            if (numero.value !== '' && (!numero.validity.valid || !Number.isInteger(Number(numero.value)))) {
                total.textContent = 'Revisa la cantidad';
                return;
            }
            const cantidad = numero.value === '' ? 0 : Number(numero.value);
            const anteriores = lista.children.length;
            while (lista.children.length > cantidad) lista.lastElementChild.remove();
            for (let indice = lista.children.length; indice < cantidad; indice++) {
                const cama = plantilla.content.firstElementChild.cloneNode(true);
                const campo = cama.querySelector('[data-tipo-cama]');
                const etiqueta = cama.querySelector('label');
                cama.id = `cama-${indice}`;
                campo.id = `tipo-cama-${indice}`;
                campo.name = `tipos_camas[${indice}]`;
                campo.value = selecciones.get(indice) || '';
                etiqueta.htmlFor = campo.id;
                etiqueta.textContent = `Tipo de la cama ${indice + 1}`;
                lista.append(cama);
            }
            if (cantidad > anteriores) lista.children[anteriores].open = true;
            else if (cantidad && !lista.querySelector('[open]')) lista.firstElementChild.open = true;
            total.textContent = numero.value === '' ? 'Sin registrar' : String(cantidad);
            actualizarResumen();
        };

        numero.addEventListener('input', ajustarCantidad);
        lista.addEventListener('change', () => {
            recordarSelecciones();
            actualizarResumen();
        });
        // Abre la sección que contiene el campo inválido para que la validación del navegador sea visible.
        lista.addEventListener('invalid', () => {
            lista.querySelector('[data-tipo-cama]:invalid')?.closest('[data-cama]').setAttribute('open', '');
        }, true);
        ajustarCantidad();
    });
}
