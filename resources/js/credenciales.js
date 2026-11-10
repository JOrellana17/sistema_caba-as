const selector = document.querySelector('[data-metodo-acceso]');
if (selector) {
    const actualizar = () => {
        const pin = selector.value === 'pin';
        document.querySelector('[data-acceso-pin]').hidden = !pin;
        document.querySelector('[data-acceso-contrasena]').hidden = pin;
        document.querySelector('[name="pin"]').disabled = !pin;
        document.querySelector('[name="pin"]').required = pin;
        document.querySelector('[name="contrasena"]').disabled = pin;
        document.querySelector('[name="contrasena"]').required = !pin;
    };
    selector.addEventListener('change', actualizar);
    actualizar();
}
document.querySelectorAll('[data-credencial]').forEach((grupo) => {
    const formulario = grupo.closest('form');
    const campo = formulario.elements.namedItem(grupo.dataset.credencial);
    const mensaje = grupo.querySelector('[data-resultado]');
    grupo.querySelector('[data-generar]').addEventListener('click', async (evento) => {
        evento.currentTarget.disabled = true;
        mensaje.textContent = 'Generando…';
        try {
            const respuesta = await fetch(grupo.dataset.url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': formulario.elements.namedItem('_token').value},
                body: JSON.stringify({contexto: grupo.dataset.contexto, tipo: grupo.dataset.credencial, nombre: formulario.elements.namedItem('nombre')?.value}),
            });
            if (!respuesta.ok) throw new Error('Revisa el nombre y tus permisos; vuelve a intentarlo.');
            const datos = await respuesta.json();
            campo.value = datos.valor;
            const confirmacion = formulario.elements.namedItem(`${grupo.dataset.credencial}_confirmation`);
            if (confirmacion) confirmacion.value = datos.valor;
            mensaje.textContent = 'Generado. Copia la credencial antes de guardar.';
        } catch (error) { mensaje.textContent = error.message; }
        finally { grupo.querySelector('[data-generar]').disabled = false; }
    });
    grupo.querySelector('[data-mostrar]')?.addEventListener('click', (evento) => {
        campo.type = campo.type === 'password' ? 'text' : 'password';
        evento.currentTarget.textContent = campo.type === 'password' ? 'Mostrar' : 'Ocultar';
    });
    grupo.querySelector('[data-copiar]').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(campo.value); mensaje.textContent = 'Copiado.'; }
        catch { mensaje.textContent = 'No se pudo copiar. Selecciona el texto y cópialo manualmente.'; }
    });
});
