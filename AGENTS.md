# Instrucciones de proyecto: Cabanas El Capitan

## Objetivo y documentos

Construir el sistema administrativo desde una plantilla limpia de Laravel, siguiendo estos archivos ubicados en la raiz:

- `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`: auditoria, requisitos y criterios de aceptacion.
- `INSTRUCCIONES_AGENTE_CONSTRUCTOR_CEC.md`: orden de construccion e instrucciones operativas.

Lee ambos documentos completos antes de implementar. Si falta alguno, informa cual falta; no reconstruyas su contenido de memoria.

Las secciones 1 a 71 de la especificacion describen el legado. Para el sistema nuevo prevalecen las decisiones de las secciones 72 en adelante, incluida la busqueda de clientes de la seccion 86. Las referencias internas a `SYSTEM_SPEC.md` corresponden a `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`.

Este archivo precisa el arranque y las convenciones de implementacion. No amplia el alcance funcional. Una instruccion explicita posterior del usuario puede cambiar estas decisiones. Ante una contradiccion relevante que los documentos no resuelvan, documentala y consulta solo lo necesario para resolverla.

## Arranque sobre la carpeta existente

Inspecciona primero `composer.json`, `package.json`, los archivos de bloqueo, la estructura y el estado Git, cuando existan.

- Si la carpeta ya contiene una plantilla Laravel, trabaja sobre ella: no crees otro proyecto dentro ni sobrescribas su configuracion indiscriminadamente.
- Si la carpeta contiene solo estos documentos, crea Laravel en esta misma raiz conservando los documentos. No uses un procedimiento que requiera borrar la carpeta para instalarlo.
- Si contiene una aplicacion con funcionalidades o datos previos, informa la situacion antes de reemplazarla.
- Respeta la version de Laravel instalada si satisface los requisitos. Verifica la compatibilidad real de PHP y las dependencias; no actualices la version principal sin necesidad y sin explicar el motivo.
- Manten los archivos de bloqueo de dependencias y utiliza comandos reproducibles cuando esten disponibles.

## Base de datos vigente

Decision tecnica aprobada el 2026-10-01: **MySQL para desarrollo, despliegue y pruebas**. La base operativa local es `sistema_cabanas`; conservar sus datos, credenciales y clave de aplicacion. No volver a configurar SQLite ni utilizarlo para nuevas verificaciones.

Las pruebas fuerzan `testing` y MySQL. `Tests\Support\BaseDatosMySql` crea esquemas nuevos con prefijo `sistema_cabanas_pruebas_` y sufijo aleatorio, verifica que no existan y solo retira los creados por esa ejecucion. Las pruebas comunes revierten transacciones; DDL y concurrencia usan esquemas propios. Nunca ejecutar pruebas sobre la base operativa ni reutilizar esquemas preexistentes. No utilizar `RefreshDatabase`, `migrate:fresh` o `db:wipe`.

Conservar las migraciones ya ejecutadas, incluso la correccion historica exclusiva de SQLite que no modifica MySQL. El archivo SQLite anterior se conserva como origen historico, fuera de Git; no borrarlo ni volver a importar sus datos. Las referencias a SQLite en resultados pasados son evidencia historica, no instrucciones vigentes. Consultar `docs/PLAN_CAMBIO_MYSQL.md` y `docs/INSTALACION.md`.

## Frontend y nomenclatura

Decision tecnica de arranque: **Blade + Bootstrap 5**, con los recursos de la aplicacion compilados mediante Vite. No incorporar React, Vue, Svelte o Livewire para reconstruir estos modulos.

Utiliza un solo sistema de estilos para las pantallas finales. Si la plantilla incluye Tailwind, adapta primero las vistas que lo usan y verifica su reemplazo antes de retirar sus dependencias. No mezcles ambos sistemas en las pantallas finales ni dependas de CDN para los recursos principales de la interfaz.

Todo identificador de negocio debe estar en espanol sin tildes ni `ñ`: clases, variables, metodos propios, tablas, columnas, rutas, permisos y estados. Conserva las convenciones necesarias del framework y las interfaces de paquetes externos. La interfaz visible mantiene el espanol correctamente escrito, conforme a la seccion 73.

No dupliques un concepto con nombres ingleses y espanoles. Usa los nombres de dominio y tablas de la especificacion. Configura explicitamente Eloquent cuando el nombre de una tabla en espanol no coincida con sus convenciones.

## Reglas de dominio obligatorias

- El alojamiento es `Cabana`, tabla `cabanas`, como unidad independiente. No crear pisos, edificios, habitaciones internas ni catalogos de tipos de habitacion.
- No existe gestion de limpieza. No introducir campos, pantallas, permisos o reportes de limpieza, `smoking` o `balcon`.
- Existe una sola entidad `Reservacion`, tabla `reservaciones`. No construir `Booking` y `Reservation` paralelos.
- Convencion definitiva de estados de reservacion: `pendiente`, `confirmada`, `hospedada`, `finalizada`, `cancelada`, `no_presentado`. No usar `check_in` ni `check_out` como estados propios del dominio.
- Centraliza las transiciones de estado y rechaza las que no correspondan al flujo definido.
- Toda disponibilidad pasa por `ServicioDisponibilidad`. Toda regla de precios utiliza una fuente coherente y conserva una copia historica de los valores aplicados.
- No inventar tarifas, tasas, reglas fiscales, anticipos, politicas de cancelacion ni datos del establecimiento.
- No agregar cotizaciones, control de caja, reservas de grupos, vencimientos automaticos u otras mejoras no aprobadas en la especificacion. La unica ampliacion funcional aprobada en la ultima edicion es la busqueda de clientes.

## Busqueda y reutilizacion de clientes

Implementa la seccion 86 sin crear otra entidad `Cliente`: el cliente es el registro de `Huesped` en `huespedes`.

- Un buscador compartido permite consultar por nombre, telefono o numero de identidad.
- Nombres: coincidencia parcial sin distinguir mayusculas ni tildes. Telefono e identidad: comparacion sin separadores de formato, conservando originales y ceros iniciales.
- Consulta desde tres caracteres utiles y devuelve como maximo 20 resultados; evita que respuestas antiguas sustituyan resultados recientes.
- Muestra las coincidencias y exige seleccion explicita. Guarda el mismo `huesped_id`; no crea duplicados.
- Reutiliza el componente en reservaciones, facturas y movimientos ya definidos que requieran cliente. Conserva los otros datos del formulario al cambiar la seleccion.
- Protege la consulta con autenticacion y `huespedes.buscar`; protege cada operacion con su propio permiso. Crear un huesped requiere `huespedes.crear`.
- Revalida al guardar el registro seleccionado, su condicion y su coherencia con la reservacion o factura. Respeta las reglas sobre inactivos, lista negra y eliminacion logica.
- Seleccionar un cliente no permite registrar un pago sin su factura obligatoria ni cambia importes o saldos.

## Arquitectura, seguridad e integridad

Utiliza modelos Eloquent, validaciones de solicitudes, autorizacion explicita y servicios o casos de uso para las reglas criticas. Manten los controladores pequenos. No crees capas o abstracciones que no tengan una responsabilidad concreta.

- Valida y autoriza en el servidor; ocultar un boton no sustituye la autorizacion.
- No deduzcas permisos a partir del nombre de la ruta ni concedas permisos administrativos mediante una accion de consulta.
- Utiliza un mecanismo coherente de roles y permisos. No dupliques asignaciones mediante columnas directas y tablas pivote sin una necesidad documentada.
- Usa transacciones para operaciones financieras y conserva historial auditable de pagos y reembolsos.
- No generes consecutivos con `max(id)+1`.
- Manten la coherencia de relaciones, claves foraneas e indices conforme a los casos de uso.
- No hardcodees contrasenas, secretos o impuestos. No muestres ni registres valores sensibles del entorno o de auditoria.
- Manten CSRF, sesiones seguras, proteccion de autenticacion y proteccion SSRF en iCalendar.
- No realices llamadas externas o envies comunicaciones reales solo para verificar una pantalla; usa datos de prueba y sustitutos cuando corresponda.
- No ejecutes `migrate:fresh`, `db:wipe` ni borres datos existentes sin autorizacion explicita. Ejecuta migraciones solo tras verificar el entorno y la base de datos de destino.

## Trabajo por fases y decisiones pendientes

Sigue el orden del documento de instrucciones. Antes de comenzar, crea `docs/PLAN_IMPLEMENTACION.md` con fases, dependencias y verificaciones, y `docs/DECISIONES.md` con decisiones tecnicas y dudas pendientes.

Avanza con autonomia en decisiones tecnicas reversibles dentro del alcance. No solicites confirmacion por cada archivo o modulo. Cuando falte una regla que afecte dinero, datos, seguridad o la operacion del negocio, plantea la duda concreta y continua las partes independientes. No tomes el comportamiento defectuoso del legado como respuesta automatica.

Actualiza el plan al terminar cada fase y registra como pendiente lo que no puedas verificar. Los documentos de seguimiento no deben convertirse en una nueva fuente de requisitos que contradiga la especificacion.

## Verificacion y definicion de terminado

Comprueba los comandos disponibles en el proyecto antes de ejecutarlos. Segun su configuracion, utiliza `php artisan test` o el script de pruebas de Composer, `npm run build` y Laravel Pint si esta instalado. Usa una base de datos de pruebas separada. Documenta los comandos ejecutados y su resultado; no afirmes que una prueba paso si no la ejecutaste.

Prueba reglas criticas de autenticacion, autorizacion, disponibilidad, estados, precios, facturacion, pagos, reembolsos, iCalendar y busqueda de huespedes. Evita pruebas que solo repitan detalles de implementacion.

Una fase terminada debe tener las migraciones, relaciones, reglas, validaciones, permisos, interfaz, manejo de errores, auditoria aplicable y pruebas necesarias. Si hay navegador disponible, verifica los flujos y su visualizacion; si no, declara la limitacion sin inventar una verificacion visual.

Al cerrar cada fase, informa brevemente que funciona, que comprobaste, que queda pendiente y que decision del usuario es necesaria, si existe.
