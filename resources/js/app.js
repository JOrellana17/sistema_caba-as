// Carga Bootstrap y los comportamientos propios mediante el paquete compilado por Vite.
import 'bootstrap';
import './credenciales';
import { iniciarBuscadores } from './buscador-huesped';
import { iniciarSelectoresCamas } from './selector-camas';

// Activa los componentes presentes; cada inicializador ignora las páginas que no los contienen.
iniciarBuscadores();
iniciarSelectoresCamas();

// Lleva el foco al resumen de validación para facilitar la corrección con teclado y lector de pantalla.
document.querySelector('[data-resumen-errores]')?.focus();

// Pide confirmación antes de enviar acciones marcadas; la autorización definitiva sigue en el servidor.
document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
    formulario.addEventListener('submit', (evento) => {
        if (!window.confirm(formulario.dataset.confirmar)) {
            evento.preventDefault();
        }
    });
});
