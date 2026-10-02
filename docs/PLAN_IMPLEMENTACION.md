# Plan de implementación

Fecha de inicio: 2026-09-29.

Fuentes leídas completas: `AGENTS.md`, `INSTRUCCIONES_AGENTE_CONSTRUCTOR_CEC.md` y `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`. Prevalecen las secciones 72–86 y las precisiones de AGENTS.md. El documento de instrucciones faltaba al inspeccionar; el usuario lo agregó y se leyó antes de implementar.

## Base de datos actual y cambio transversal

**MySQL** es el motor aprobado para la aplicacion y las pruebas desde el 2026-10-01. La base operativa es `sistema_cabanas`, con datos trasladados y conservados. Ver `PLAN_CAMBIO_MYSQL.md` para configuracion, aislamiento de pruebas, concurrencia y comprobaciones del cambio. Los resultados de SQLite que aparecen mas abajo documentan ejecuciones anteriores; no indican el motor que debe utilizarse ahora.

## Inspección inicial

- Plantilla Laravel existente, sin módulos de negocio. Se trabaja en esta misma raíz.
- PHP 8.3.30; Composer disponible mediante `php C:/laragon/bin/composer/composer.phar`; Node 24.16.0; npm 11.13.0 mediante `npm.cmd` (PowerShell impide el lanzador npm.ps1).
- `composer.lock` y `package-lock.json` presentes; dependencias instaladas. No actualizar la versión principal de Laravel.
- No existe repositorio Git: `git status --short` devuelve «not a git repository». No se puede producir un diff contra un commit anterior.
- Entorno `local`, SQLite, tres migraciones base ejecutadas y cero filas en `users`, comprobado mediante consulta de conteo. No se consultaron contraseñas ni datos personales.
- Vista `welcome` y recursos iniciales con Tailwind; sustituirlos por Blade + Bootstrap 5 compilado con Vite antes de retirar Tailwind.
- Pruebas configuradas con SQLite `:memory:`, correo y sesiones en memoria. Dos pruebas de ejemplo pasan antes de los cambios.

## Fases y dependencias

| Fase | Alcance | Depende de | Verificación de cierre | Estado |
| --- | --- | --- | --- | --- |
| 1 | Base, autenticación, sesiones, layout, errores y soporte inicial de auditoría de acceso | Inspección y documentos | Acceso correcto/incorrecto, inactivos, limitación, CSRF, sesión y cierre; migración conservadora; build y Pint | Implementada y verificada automáticamente; acceso/navegación revisados en Edge, demás recorridos visuales parciales |
| 2 | Usuarios, roles, permisos explícitos y auditoría | 1; matriz de roles | Matriz de autorización, acceso directo, operaciones sensibles, privacidad de auditoría | Implementada y verificada automáticamente; acceso/navegación revisados en Edge, demás recorridos visuales parciales |
| 3 | Cabañas, estados, imágenes, eliminación lógica y restauración | 2 | CRUD, capacidades, permisos, imágenes y restauración | Implementada y verificada automáticamente; alta revisada en Edge; imágenes/restauración visual pendientes |
| 4 | Huéspedes y buscador compartido | 2–3; reglas de clientes | Tres criterios, tildes, separadores, ceros, literales, máximo 20, permisos, teclado y respuestas atrasadas | Implementada y verificada automáticamente; integración en reservaciones verificada; buscador revisado en Edge; alta en otra pestaña e integraciones de fases 7 y 8 pendientes |
| 5 | Reservaciones, disponibilidad, precios históricos y estados | 3–4; reglas operativas y de precios | Cruces, concurrencia, capacidad, transiciones, selección sin duplicados | Implementada y verificada automáticamente; concurrencia MySQL probada; alta/reagendado/confirmación/cancelación revisados en Edge; otros recorridos visuales pendientes |
| 6 | Servicios y asociación a reservaciones | 5 | Cantidades, precios históricos, totales y permisos | Pendiente |
| 7 | Facturas, detalles, impuestos, descuentos y PDF | 5–6; reglas fiscales | Cálculos, redondeo, snapshots, permisos y coherencia huésped/reservación | Pendiente |
| 8 | Pagos y reembolsos | 7; métodos y reglas financieras | Parciales/completos/excesos, concurrencia, factura obligatoria, historial y auditoría | Pendiente |
| 9 | Calendario e iCalendar | 5–8 | Conflictos, duplicados, UID, fechas, tokens, SSRF y límites con sustitutos de red | Implementación pendiente; investigación y [plan detallado](PLAN_CALENDARIO_ICAL.md) preparados |
| 10 | Reportes operacionales, financieros y estadísticos | 5–9 | Filtros, fuentes únicas, totales, permisos, PDF y Excel | Pendiente |
| 11 | Configuración | Fases previas; datos reales del establecimiento | Tipos, permisos, secretos y coherencia con servicios consumidores | Pendiente |
| 12 | Panel | 3–11 | Indicadores y alertas conciliados con fuentes de dominio y permisos | Pendiente |
| 13 | Revisión integral | 1–12 | Suite completa, seguridad, compilación, formato y recorridos visuales si hay navegador | Pendiente |

La configuración mínima requerida por una fase se introduce junto a su consumidor; su administración integral corresponde a la fase 11. El inicio autenticado de la fase 1 no adelanta métricas del panel ni concede permisos administrativos.

## Buscador y dependencias transversales

Crear `ServicioBusquedaHuespedes` y componente Blade en fase 4, reutilizarlos en fases 5, 7 y 8. El cliente siempre es `Huesped`, con selección explícita y revalidación al guardar. Conservar originales y ceros; no crear registros al buscar. Las reglas completas son las de la sección 86, incluidos errores diferenciados, preservación de formularios y exclusión de eliminados.

## Protocolo de cierre

Cada fase requiere esquema, modelos, validación, autorización, reglas, UI, manejo de errores, auditoría aplicable y pruebas. Registrar comandos y resultados reales. No ejecutar migraciones sobre otra base sin comprobar destino. No ejecutar `migrate:fresh`, `db:wipe` ni borrar datos existentes. Las dudas afectan únicamente a las partes dependientes; se registran en `DECISIONES.md`.

## Comprobaciones ejecutadas

| Comprobación | Resultado |
| --- | --- |
| `php C:/laragon/bin/composer/composer.phar check-platform-reqs` | Correcto: PHP y extensiones satisfacen las dependencias instaladas |
| `php artisan list --raw` | Disponibles test, migrate, migrate:status, route:list y view:cache, entre otros |
| `php artisan env` | local |
| `php artisan migrate:status` | Tres migraciones iniciales ejecutadas |
| Esquema y `SELECT COUNT(*) AS total_usuarios FROM users` mediante Boost | SQLite, tablas base; 0 usuarios |
| `php artisan test`, antes de implementar | 2 pruebas, 2 aserciones, correctas |

## Cierre de fase 1

Implementado:

- `Usuario` y tabla `usuarios`, mediante migración que conserva filas, IDs y hashes. Prueba específica con una fila previa en una segunda conexión SQLite en memoria.
- Acceso por usuario y contraseña, opción de recuerdo, límite de cinco fallos por usuario/IP durante un minuto, regeneración de sesión, cierre por POST y CSRF.
- Revocación de cuentas inactivas en solicitudes protegidas, incluida recuperación mediante guard de sesión. Protección de sesiones ante cambios de contraseña mediante `auth.session`.
- Validaciones y formularios en español; contraseña excluida de datos antiguos y serialización. Manejo de entradas malformadas y escape de texto recibido.
- Auditoría mínima de accesos exitosos/fallidos, cierre y revocación. No existe aún consulta web de auditoría: depende de los permisos de fase 2.
- Comando `usuarios:crear`, interactivo, contraseña oculta sin fallback visible, confirmación, validación de duplicados y transacción. Sin cuenta predeterminada ni asignación automática de permisos.
- Portada, formulario de acceso, inicio autenticado, mensajes compartidos y páginas de error. Bootstrap 5.3.8 y Vite; Tailwind retirado después de verificar el reemplazo. Sin fuentes ni estilos externos de CDN.
- Guía de uso local en `docs/INSTALACION.md`.

### Comprobaciones finales

| Comando o comprobación | Resultado real |
| --- | --- |
| `npm.cmd install bootstrap@5.3 --save-exact --ignore-scripts --no-audit --no-fund` | Primer intento bloqueado por restricciones de red/caché; repetición autorizada correcta, Bootstrap 5.3.8 fijado en lock |
| `npm.cmd run build`, antes de retirar Tailwind | Correcto con las vistas y CSS sustituidos |
| `npm.cmd uninstall tailwindcss @tailwindcss/vite --ignore-scripts --no-audit --no-fund` | Correcto, retiradas las dependencias de Tailwind |
| `php C:/laragon/bin/composer/composer.phar dump-autoload --no-scripts` | Correcto, 8059 clases; sin actualizar dependencias |
| `php artisan test --compact` | Última ejecución: 37 pruebas correctas, 237 aserciones, SQLite en memoria |
| `php vendor/bin/pint --test` | Correcto |
| `npm.cmd run build`, después de retirar Tailwind | Correcto, Vite 8.3.1, 61 módulos transformados |
| `php artisan view:cache` | Todas las vistas Blade compilaron correctamente |
| `php artisan route:list --except-vendor` | Cinco rutas de aplicación: portada, acceso GET/POST, inicio y cierre POST |
| `php artisan db:show --database=sqlite` | Destino confirmado: `C:\laragon\www\sistema_cabañas\database\database.sqlite`, SQLite 3.40.0 |
| `php artisan migrate --no-interaction` | Las dos migraciones nuevas se aplicaron correctamente en el entorno local verificado |
| `php artisan migrate:status` posterior | Cinco migraciones ejecutadas, ninguna pendiente |
| Búsqueda de referencias activas a `App\Models\User`, `UserFactory`, Tailwind, Bunny y CDN | Sin coincidencias en código/recursos/configuración frontend revisados |

Una ejecución intermedia de la suite falló al comprobar mensajes después de iniciar manualmente la sesión de pruebas mediante una aserción y realizar otra solicitud. La prueba se ajustó para seguir la redirección completa sin alterar ese ciclo; luego pasaron las pruebas afectadas y la suite completa. No se debilitó la serialización JSON de sesiones.

La inspección mediante Tinker no mostró el resultado porque PsySH intentó escribir historial fuera del workspace; el destino se comprobó mediante `db:show`, que no necesita ese historial.

No se ejecutaron migraciones destructivas, seed de cuentas reales ni comunicaciones externas. No hay navegador automatizable disponible; quedaron verificados el renderizado mediante solicitudes HTTP de pruebas y la compilación, pero no se afirma una revisión visual de escritorio/móvil. No existe Git, por lo que tampoco hay commit ni diff contra historial previo.

La siguiente tarea al cerrar la fase 1 era la fase 2, completada a continuación.

## Cierre de fase 2 — 2026-09-30

Implementado:

- Usuarios: alta, consulta, edición de datos, activación/desactivación, eliminación lógica, restablecimiento de contraseña y asignación separada de roles y permisos directos.
- Roles y permisos: catálogo explícito de 69 permisos conforme a las secciones 77 y 86; incluye `huespedes.buscar`. CRUD de roles, gestión de sus permisos, catálogo editable y protección de códigos del sistema. Los permisos de módulos posteriores no implican que esos módulos estén implementados.
- Autorización con Gates y middleware `can` explícitos en cada ruta. Revalidación dentro de las transacciones administrativas. Un operador no concede permisos que no posee ni modifica cuentas administradoras sin ser administrador.
- Protección del último administrador activo, del rol Administrador y de cuentas administradoras inactivas. Asignaciones y auditoría se guardan en la misma transacción; un bloqueo común serializa modificaciones de privilegios.
- Auditoría paginada, filtros, detalle, autor conservado mediante eliminación lógica y lista de campos autorizados para el historial. Sin modificación o eliminación por HTTP; contraseñas, hashes, tokens y correos no se registran en las copias de cambios.
- Menú y formularios Blade + Bootstrap sujetos a permisos, mensajes accesibles y confirmación de eliminación. Idioma local ajustado a español únicamente mediante `APP_LOCALE` y `APP_FALLBACK_LOCALE`; sin modificar claves ni conexión de base.
- Comando interactivo `usuarios:crear-administrador`: no reemplaza cuentas existentes, no inventa correo y recibe la contraseña de forma oculta. La excepción de longitud inicial fue solicitada explícitamente para esta cuenta; las reglas generales permanecen iguales.
- Se guarda la referencia de contraseña en sesión desde el inicio de sesión, para invalidar sesiones si la contraseña cambia incluso antes de visitar la primera página protegida.

### Comprobaciones ejecutadas

| Comando o comprobación | Resultado real |
| --- | --- |
| Inspección de entorno y `php artisan db:show --database=sqlite --counts` | local; `C:\laragon\www\sistema_cabañas\database\database.sqlite`; 0 usuarios antes del alta autorizada |
| Pruebas iniciales de usuarios/autorización | 39 pruebas correctas, 341 aserciones |
| Pruebas de roles/permisos/auditoría/alta administrativa | 15 pruebas correctas, 101 aserciones |
| `php artisan test --compact`, suite final tras los ajustes adicionales | **95 pruebas correctas, 698 aserciones**, en SQLite en memoria |
| `npm.cmd run build` | Correcto, Vite 8.3.1, 61 módulos transformados |
| `php vendor/bin/pint --test` | Correcto |
| `php artisan view:cache` | Correcto |
| `php artisan route:list --except-vendor` | 32 rutas de aplicación |
| `php artisan migrate --no-interaction` | Dos migraciones de fase 2 aplicadas, sin borrar registros anteriores |
| `php artisan db:seed --class=PermisosSeeder --no-interaction` | Catálogo y rol Administrador cargados correctamente |
| `php artisan usuarios:crear-administrador admin --longitud-minima=8` | Alta autorizada completada mediante contraseña oculta y confirmación; no se almacenó la contraseña en archivos |
| Consulta posterior de usuario/rol/conteos, sin leer credenciales | `admin` activo, rol `administrador`, **69 de 69 permisos** asignados |
| `php artisan migrate:status` posterior | Siete migraciones ejecutadas, ninguna pendiente |

No se afirma una revisión visual: no había navegador automatizable disponible. Se verificaron las pantallas mediante pruebas HTTP y compilación Blade. No se ejecutó una prueba de concurrencia multiproceso contra un motor de producción; debe verificarse el motor definitivo antes del despliegue. No hay Git en esta carpeta.

La siguiente tarea al cerrar la fase 2 era la fase 3, Cabañas, completada a continuación. Los roles adicionales del personal siguen sin asignarse hasta definir su matriz real; pueden configurarse desde la interfaz. La política de retención de auditoría sigue pendiente y no hay borrado automático. El buscador compartido corresponde a fase 4 y sus integraciones a fases 5, 7 y 8.

## Cierre de fase 3 — 2026-09-30

Implementado:

- Catálogo `Cabana`/`cabanas`: listado paginado, búsqueda literal por nombre, filtros por estado y activación, alta, detalle y edición. Sin pisos, tipos de habitación ni limpieza.
- Capacidades positivas y coherentes; tarifas explícitas con hasta dos decimales, sin redondeo silencioso ni valores predeterminados. Slug único generado con UUID, estable al editar, y relación con el usuario creador.
- Los cinco estados operativos pueden asignarse manualmente: disponible, ocupada, reservada, mantenimiento y bloqueada. Decisión confirmada por el usuario. Estado y activación requieren su permiso separado de la edición ordinaria.
- Imágenes privadas JPEG/PNG/WebP, validación de contenido, extensión, tamaño y dimensiones. Nombres generados, consulta autenticada, cabeceras de privacidad y rechazo de SVG o archivos ejecutables.
- Eliminación lógica y restauración conservando identificador, creador, tarifas, estado e imagen. No existe eliminación definitiva por HTTP.
- Transacciones con revalidación de permisos y auditoría de creación, edición, estado, eliminación y restauración. Ante un fallo, se revierte la escritura y se retira únicamente la imagen nueva de esa operación; las imágenes previas se conservan.
- Formularios y mensajes en español, navegación por permisos, conservación de entradas ante errores y confirmación de eliminación/restauración. Permisos del listado calculados sin repetir consultas por cada fila.
- Sistema interno confirmado por el usuario: el personal gestiona la operación y las actualizaciones iCalendar se incorporarán en fase 9; no hay reserva pública.

### Comprobaciones ejecutadas

| Comando o comprobación | Resultado real |
| --- | --- |
| Pruebas iniciales de Cabañas | 50 de 54 correctas; cuatro fallos en preparación de pruebas (datasets, archivo simulado y comparación del modelo); corregidos |
| Repetición de casos afectados | 8 pruebas correctas, 56 aserciones |
| `php artisan test --compact tests/Feature/Http/Controllers/CabanaControllerTest.php` | **56 pruebas correctas, 412 aserciones** |
| `php artisan test --compact`, suite completa final | **151 pruebas correctas, 1110 aserciones**, SQLite en memoria; incluye las fases anteriores |
| `php vendor/bin/pint tests/Feature/Http/Controllers/CabanaControllerTest.php` y `php vendor/bin/pint --test` | Importación ajustada por el formateador; comprobación final correcta |
| `npm.cmd run build` | Correcto, Vite 8.3.1, 61 módulos transformados |
| `php artisan view:cache`, tras los cambios finales | Correcto |
| `php artisan route:list --path=cabanas --except-vendor` | Diez rutas del módulo, todas bajo autenticación y permiso explícito |
| `php artisan env` y `php artisan db:show --database=sqlite --counts` | Entorno local; destino confirmado `C:\laragon\www\sistema_cabañas\database\database.sqlite`; un usuario y siete migraciones antes del cambio |
| `php artisan migrate --no-interaction` | Migración aditiva de Cabañas aplicada correctamente |
| `php artisan migrate:status` | Ocho migraciones ejecutadas; ninguna pendiente |
| Consulta posterior de conteos y rol, sin credenciales | Cero cabañas precargadas; un usuario; `admin` activo con rol Administrador y 69 permisos; auditoría previa conservada |

Una primera ejecución de la suite completa perdió su sesión de consola antes de devolver el resultado; se repitió y el resultado informado corresponde a la ejecución terminada. No se atribuye éxito a la ejecución interrumpida.

No se dispone de navegador automatizable: se verificaron renderizado HTTP, validación y compilación, pero la revisión visual de escritorio/móvil sigue pendiente. No se ensayó concurrencia multiproceso sobre el motor de producción. No se borraron datos existentes ni se restableció la contraseña administrativa.

La disponibilidad por fechas se implementará con `ServicioDisponibilidad` y reservaciones en fase 5. No se presenta el estado operativo como confirmación de disponibilidad. Las restricciones de cambios sobre cabañas con reservaciones y la precedencia de conflictos iCalendar se resolverán antes de habilitar esas integraciones.

Al cerrar la fase 3, la siguiente fase era **4, Huéspedes y buscador compartido de la sección 86**, completada a continuación con las reglas confirmadas por el usuario.

## Cierre de fase 4 — 2026-09-30

Implementado:

- Una entidad `Huesped`/`huespedes`, sin Cliente paralelo. Alta, edición, ficha, listado paginado y filtros; nacionalidad, notas internas/incidentes, VIP, frecuente, lista negra y activación. Eliminación lógica y restauración conservando el mismo identificador y condiciones.
- Nombre, teléfono e identidad obligatorios en altas/ediciones, según respuesta del usuario. Teléfono internacional sin formato nacional fijo ni unicidad; identidad comparable única también entre eliminados, con índice único y manejo de colisiones. Originales y ceros preservados.
- Inactivos y lista negra pueden seleccionarse con advertencia. Identidad completa visible a quienes pueden ver o buscar huéspedes. La consulta no concede permisos.
- `ServicioBusquedaHuespedes` compartido por listado y endpoint JSON: nombre parcial sin tildes/mayúsculas, teléfono/identidad sin separadores, términos literales, mínimo tres caracteres útiles, prioridad de coincidencias exactas y máximo 20 resultados. Excluye eliminados y no devuelve notas privadas ni información financiera.
- Componente Blade con JavaScript nativo: estados de carga, ausencia de resultados, error y reintento; selección explícita por clic/teclado; mismo `huesped_id`; invalidación al editar texto; cancelación y descarte de respuestas antiguas. Alta autorizada en otra pestaña para conservar el formulario de origen, sin selección automática ni borradores persistentes.
- Operaciones administrativas con permiso explícito, revalidación y transacción. Estado, lista negra y frecuente separados de la edición ordinaria. Auditoría sin valores de documentos, teléfonos, nombres ni notas; historial de cambios de ficha sujeto a `auditoria.ver`.
- `ServicioSeleccionHuesped` preparado para releer el registro en la transacción de las futuras operaciones; rechaza inexistentes/eliminados y conserva las condiciones permitidas. No crea huéspedes ni modifica dinero.

### Verificaciones ejecutadas

| Comando o comprobación | Resultado real |
| --- | --- |
| Inspección de dependencias, locks y Git | Se conservaron dependencias; ahora existe Git con archivos sin seguimiento. No se crearon commits ni se modificó el historial |
| Pruebas iniciales del servicio de búsqueda | 17 pruebas correctas, 77 aserciones |
| Pruebas de controlador y servicio, ejecución inicial conjunta | 66 pruebas correctas, 421 aserciones |
| `php artisan test --compact`, tras cobertura adicional de alta mínima y presentación del valor cero | **219 pruebas correctas, 1541 aserciones**, incluidas 68 pruebas nuevas; SQLite en memoria |
| `node --test tests/js/buscador-huesped.test.js` | **10 pruebas correctas**, incluidas carreras entre respuestas, teclado, conservación de datos, errores y advertencias |
| `php vendor/bin/pint` sobre archivos nuevos y `php vendor/bin/pint --test` | Se corrigieron espacios/formato; comprobación final correcta |
| `npm.cmd run build` | Correcto, Vite 8.3.1, 62 módulos transformados |
| `php artisan view:cache` | Correcto después de las vistas finales |
| `php artisan route:list --path=huespedes --except-vendor` | Trece rutas protegidas del módulo |
| `php artisan env`, `php artisan db:show --database=sqlite --counts`, `php artisan migrate:status` antes de migrar | Local; destino `C:\laragon\www\sistema_cabañas\database\database.sqlite`; ocho migraciones ejecutadas y solo Huéspedes pendiente |
| `php artisan migrate --no-interaction` y estado posterior | Migración aditiva aplicada; nueve migraciones ejecutadas, ninguna pendiente |
| Consulta posterior de conteos y rol, sin credenciales | Cero huéspedes/cabañas precargados, un usuario y registro de auditoría previo conservados; admin activo con 69 permisos |

Las pruebas JavaScript usan un sustituto mínimo del DOM, no un navegador real. No hay navegador automatizable disponible; revisión visual y recorrido real de dos pestañas pendientes. La compilación y las pruebas HTTP verificaron las vistas. No se ensayó concurrencia multiproceso sobre el motor de producción; sí la restricción única y la reversión ante fallo de auditoría.

No se borraron datos existentes ni se cambió la contraseña administrativa. La búsqueda solo consulta y selecciona; no registra reservaciones, facturas o pagos.

Siguiente fase: **5, Reservaciones y disponibilidad**. Allí se integrará el componente y se comprobará la asociación real del mismo huésped. Facturas/pagos e historiales financieros se conectarán en fases 7 y 8, con coherencia de relaciones y factura obligatoria para pagos. Las decisiones pendientes de horarios, estados que bloquean disponibilidad, reglas de precios y moneda deben resolverse antes de las partes afectadas.

## Cierre de fase 5 — 2026-10-01

Reservaciones y disponibilidad implementadas sobre esta aplicación. Incluye listado/ficha, alta con selección explícita del mismo Huesped, edición/reprogramación, confirmación, entrada, salida, cancelación, no presentado, horarios excepcionales autorizados y eliminación lógica. La ficha del huésped muestra sus reservaciones con permiso propio.

Las decisiones posteriores del usuario prevalecen: **por_confirmar y pendiente no bloquean**; confirmada y hospedada sí. Los estados manuales ocupada/reservada, mantenimiento/bloqueada, inactividad, capacidad y cruces se comprueban mediante ServicioDisponibilidad. Al guardar/confirmar se repite la comprobación bajo bloqueo; una entrada física no permite otra estancia hospedada sin cerrar.

Tarifas HNL/USD independientes en el catálogo y una lista para elegir moneda. Precio por noche: base más personas adicionales sobre capacidad base. Al reagendar puede mantenerse la tarifa histórica o aplicarse la actual. No hay conversión, cargos por horarios, impuestos o penalizaciones inventados.

El buscador conserva la selección tras errores y los campos del formulario al cambiar de cliente o abrir el alta en otra pestaña. Inactivos/lista negra seleccionables con advertencia; eliminados rechazados al guardar/confirmar. La auditoría conserva relaciones/estados/horarios/importes sin notas ni valores personales. Las estancias registradas no se eliminan; las cabañas con reservaciones vigentes quedan protegidas frente a borrado o disminución incompatible de capacidad.

### Comprobaciones ejecutadas

| Comando o comprobación | Resultado real |
| --- | --- |
| Inspección de dependencias, configuración y Git | Laravel y archivos de bloqueo conservados; Git sigue sin archivos de aplicación registrados. No se instalaron paquetes, crearon commits ni reemplazó configuración |
| Pruebas iniciales de disponibilidad/componente | Se corrigieron la preparación de errores de Blade y un error de comparación de fechas malformadas. Ejecución posterior: 50 pruebas, 180 aserciones |
| Pruebas de ReservacionController | Tras corregir compilación de vista y preparación de sesiones al cambiar de actor: 46 pruebas, 327 aserciones; después se añadió cobertura de ocupación real, historial y entradas malformadas |
| Dos procesos PHP con confirmaciones simultáneas | Correcto: una confirmación y un rechazo, un solo cambio auditado. Archivo SQLite temporal separado, con usuarios y datos ficticios; no se usó la base local |
| Prueba de precisión monetaria en SQLite | Detectó pérdida de un centavo con DECIMAL de 16 cifras. Se corrigió el almacenamiento del subtotal mediante migración adicional y la prueba pasó |
| `php artisan test --compact`, ejecución final tras esa corrección | **326 pruebas correctas, 2108 aserciones**, incluidas 107 nuevas; base en memoria y un archivo temporal exclusivo de concurrencia |
| `node --test tests/js/buscador-huesped.test.js` | **10 pruebas correctas**, con teclado, carreras entre respuestas, errores, advertencias y conservación de otros campos |
| `php vendor/bin/pint` sobre archivos modificados y `php vendor/bin/pint --test` | Formato corregido donde correspondía; comprobación final correcta |
| `npm.cmd run build` | Correcto, Vite 8.3.1, 62 módulos transformados |
| `php artisan view:cache` | Correcto con las vistas de reservaciones y el historial del huésped |
| `php artisan route:list --path=reservaciones --except-vendor` | Quince rutas con autenticación y permiso de operación; la consulta desde Cabañas tiene su permiso independiente |
| `php artisan env`, `php artisan db:show --database=sqlite --counts` y estados de migración | Se verificó local y destino `C:\laragon\www\sistema_cabañas\database\database.sqlite`, con nueve migraciones antes de iniciar y cero cabañas/huéspedes |
| `php artisan migrate --no-interaction`, primera aplicación | Creación de reservaciones y adición de tarifas USD; once migraciones ejecutadas |
| `php artisan db:seed --class=PermisosSeeder --no-interaction` | Tres permisos añadidos al catálogo y al rol administrador, sin recrear usuarios ni cambiar contraseñas |
| `php artisan migrate --no-interaction`, corrección monetaria y estado posterior | Corrección exacta de subtotal aplicada; **doce migraciones ejecutadas, ninguna pendiente** |
| Consulta final de conteos y estado administrativo, sin credenciales | Un usuario, admin activo con **72 permisos**, registro previo de auditoría conservado; cero cabañas, huéspedes o reservaciones precargados |

Se preservaron los datos locales y la contraseña administrativa. No se ejecutaron comandos de borrado, rollback, reinicio de base, comunicaciones ni llamadas externas.

Pendientes: revisión visual y recorrido real de alta en otra pestaña, porque no hay navegador automatizable disponible. Las pruebas JavaScript usan un sustituto del DOM. La concurrencia multiproceso sí se ensayó sobre SQLite; falta ensayar el motor de despliegue si se decide usar otro. Impuestos, descuentos, facturas, pagos e iCalendar conservan sus verificaciones pendientes en sus fases.

Siguiente fase: **6, Servicios adicionales y asociación a reservaciones**. No quedan preguntas operativas pendientes para esta fase 5; las reglas fiscales, métodos de pago e integraciones se consultarán antes de las partes que dependan de ellas.

## Cambio de base a MySQL — 2026-10-01

Solicitud del usuario atendida: la aplicación usa MySQL en `sistema_cabanas`, conservando los datos del archivo SQLite anterior. Se limpiaron las credenciales de las salidas de comprobación; no se publicaron valores sensibles. Tras el error inicial de autenticación 1045, el usuario corrigió el acceso directamente en `.env`.

| Comando o comprobación | Resultado real |
| --- | --- |
| Revisión de las doce migraciones, configuración y extensiones PHP | Esquemas de Laravel compatibles con MySQL; pdo_mysql disponible; sin cambios en código o dependencias |
| `php artisan config:clear` | Correcto; la aplicación carga los valores actualizados del entorno |
| Conexión PDO y consulta de esquema destino | MySQL **8.0.46**; `sistema_cabanas` no existía antes de crearla |
| Creación de `sistema_cabanas` | Nueva base con utf8mb4/utf8mb4_unicode_ci; sin alterar otras bases |
| `php artisan migrate --pretend --no-interaction` | Generación de SQL MySQL correcta |
| `php artisan migrate --no-interaction` | **Doce migraciones aplicadas correctamente en MySQL**, sin modificar sus archivos |
| Transferencia desde SQLite de solo lectura | Diecisiete tablas de datos copiadas en una transacción sobre destino vacío; claves foráneas habilitadas, sin reemplazos ni borrados |
| Comparación de filas y columnas antes de confirmar | Contenido equivalente, incluidos identificadores, hash de contraseña, permisos y auditoría; sesiones/caché conservadas |
| `php artisan migrate:status` | Doce migraciones ejecutadas, ninguna pendiente |
| Consulta posterior mediante Eloquent/Gate y metadatos | Admin activo con **72 permisos autorizados**, tablas InnoDB y subtotal **DECIMAL(16,2)** |
| Consulta de `ServicioBusquedaHuespedes` en MySQL | Ejecutada sin error; cero resultados porque aún no hay huéspedes |
| `php artisan test --compact tests/Feature/Database/MigracionUsuariosTest.php tests/Feature/Services/ServicioPreciosReservacionTest.php tests/Feature/Seguridad/AutorizacionTest.php` | **32 pruebas correctas, 287 aserciones**, en SQLite en memoria incluso con MySQL configurado para la aplicación |

No se ejecutaron pruebas que borren o reinicien bases; no se cambió la contraseña administrativa. SQLite permanece disponible como origen conservado. Los scripts puntuales de inspección/transferencia se retiraron al terminar; no se añadió una funcionalidad de importación al sistema.

Pendientes: concurrencia y suite de integración sobre una base MySQL de pruebas separada; revisión visual ya registrada. Las pruebas de SQLite y la ejecución real de las migraciones no sustituyen esa cobertura. No se repitió la compilación de frontend ni Pint porque solo se modificaron `.env` y estos documentos, sin cambios permanentes de PHP, vistas o recursos.

La siguiente fase funcional sigue siendo **6, Servicios adicionales y asociación a reservaciones**.

## Cierre del cambio definitivo a MySQL — 2026-10-01

Plan solicitado creado e implementado: `PLAN_CAMBIO_MYSQL.md`. Configuración de aplicación, ejemplos, PHPUnit, pruebas de migración/concurrencia, soporte, instalación e instrucciones de agentes alineados con MySQL. Los resultados de SQLite de secciones anteriores son históricos.

Verificación final: **333 pruebas PHP y 1800 aserciones en MySQL**, diez pruebas JavaScript, Pint, Vite, caché Blade y validación de Composer correctos. La concurrencia multiproceso confirmó una sola reservación para el mismo intervalo y una sola auditoría. Se añadieron protecciones del destino de pruebas y una prueba que detectó la comparación indebida de letras con tildes en identidad; una migración nueva conservó la comparación existente.

Trece migraciones ejecutadas en `sistema_cabanas`, ninguna pendiente. Admin activo con 72 permisos, datos previos y archivo SQLite anterior conservados. Cero esquemas de pruebas restantes. No se cambiaron contraseñas, transfirieron datos otra vez ni reiniciaron bases existentes.

La concurrencia y la suite MySQL dejan de ser pendientes del cambio de motor. Sigue pendiente la revisión visual; servicios, facturas, pagos, reembolsos e iCalendar se verificarán sobre MySQL durante sus fases. La siguiente fase funcional continúa siendo **6, Servicios adicionales**.

## Recreación solicitada por el usuario — 2026-10-01

El usuario informó que eliminó la base MySQL para ejecutar nuevamente las migraciones y pidió reconstruirla. Se comprobó `local`, conexión MySQL y destino `sistema_cabanas`, inexistente y sin tablas. Se creó únicamente esa base con utf8mb4/utf8mb4_unicode_ci; no se eliminaron ni alteraron otras bases.

Comandos ejecutados: `php artisan config:clear`, `php artisan migrate --no-interaction`, `php artisan db:seed --class=PermisosSeeder --no-interaction` y `php artisan migrate:status`. Resultado: **13 migraciones aplicadas**, ninguna pendiente; rol Administrador y **72 permisos** preparados.

El usuario autorizó después recuperar **únicamente el admin anterior** desde el archivo conservado. La recuperación puntual se realizó en una transacción, conservando ID, datos de la cuenta y hash de contraseña; asignando el rol Administrador del destino y registrando `administrador_restaurado_por_consola` en auditoría. No se reutilizó el token de recordar ni se importaron sesiones, caché, datos de negocio o auditoría anterior. El archivo conservado se leyó en modo de solo lectura y quedó intacto. Esta autorización puntual no habilita repetir una importación general.

Comprobaciones posteriores: el proveedor de autenticación encuentra al admin activo; sus **72 permisos** están autorizados y el hash coincide con el original, sin imprimirlo. Un usuario, un rol, 72 permisos, una asignación de rol y un registro de auditoría. Cero cabañas, huéspedes, reservaciones, sesiones y entradas de caché. Identidad `utf8mb4_bin` y subtotal `DECIMAL(16,2)` correctos.

No se modificó código, claves, credenciales ni migraciones. No se ejecutaron `migrate:fresh`, `db:wipe` o rollback. Los scripts puntuales se retiraron al terminar. No se repitió la suite: la verificación de este paso fue la aplicación real de las migraciones y las comprobaciones anteriores; la suite MySQL de 333 pruebas corresponde al cambio de motor previo.

El esquema implementado cubre las fases 1 a 5 y las tablas de infraestructura. Servicios, facturación, pagos y las demás fases siguen pendientes; esta reconstrucción no crea sus funcionalidades.

## Pruebas en navegador real — 2026-10-01

Recorrido correcto en Edge 154, escritorio 1366 × 900 y viewport móvil 390 × 844, con MySQL efímero y datos ficticios. Diez comprobaciones agrupadas: autenticación, navegación administrativa, alta de cabaña/huésped, tres criterios del buscador con teclado y advertencias, conservación de campos, reservación, reagendado, confirmación, cancelación y cierre de sesión. Persistencia comprobada: un huésped compartido, una cabaña y subtotal exacto HNL 271.50. Ninguna excepción JavaScript ni error HTTP observado.

Capturas del buscador, ficha confirmada y listado móvil revisadas. La revisión visual deja de estar completamente pendiente, pero no se considera exhaustiva. Alta en otra pestaña, imágenes/restauración, administración de roles mediante formularios y demás estados operativos conservan sus recorridos pendientes. Detalles, comandos, evidencias y limitación de CSRF en testing: `PRUEBAS_INTERFAZ.md`.

Pint de los dos nuevos archivos PHP y `node --check` correctos. Cero esquemas temporales al terminar; servidor del usuario en 8000 responde HTTP 200. Sin cambios en `.env`, funcionalidades, recursos, credenciales ni datos operativos. No se repitieron la suite completa ni el build. Fase siguiente: **6, Servicios adicionales**.

## Ajuste de reservaciones e inicio solicitado — 2026-10-01

Implementado: recuperación del buscador al servir el build correcto; alta de huésped dentro del formulario de reservación; nombre/teléfono obligatorios e identidad opcional; tarjetas de acceso en Inicio según permisos y rutas existentes. El alta reutiliza validación, servicio, transacción y auditoría de huéspedes, mantiene el formulario y asigna el mismo huesped_id. La identidad registrada sigue siendo única; no se usa el teléfono como identificador único.

Se comprobó MySQL y que las dos columnas de identidad ya admiten null. Sin migraciones necesarias ni modificación de registros reales. El marcador temporal `public/hot` apuntaba a Vite sin conexión: se verificó el fallo, se compiló y se retiró solamente ese marcador. El servidor en 8000 y sus recursos compilados respondieron HTTP 200.

| Comando o comprobación | Resultado real |
| --- | --- |
| Suite específica inicial de huéspedes, reservaciones, consulta y vistas | 127 de 129 correctas; dos comprobaciones nuevas corregidas (sesión al alternar usuarios y coincidencia parcial de URL en la aserción) |
| Pruebas específicas nuevas de alta y tarjetas | 10 correctas, 80 aserciones; incluyen autenticación, ambos permisos, campos sensibles, duplicado de identidad y CSRF real |
| `php artisan test --compact`, primera ejecución completa | 342 de 343 correctas; una aserción de autenticación comparaba el texto antiguo de Inicio retirado por solicitud del usuario |
| `php artisan test --compact`, tras actualizar la aserción de Inicio conservando la comprobación de autenticación | **343 pruebas correctas, 1884 aserciones, MySQL separado** |
| Ajuste final de texto Sin registrar en ficha de reservación; `php artisan test --compact tests/Feature/Http/Controllers/AltaHuespedEnFormularioTest.php` | **8 correctas, 67 aserciones**; no se repitió toda la suite por este ajuste de presentación |
| `node --test tests/js/buscador-huesped.test.js` | **13 correctas**, incluyendo alta, CSRF en petición, doble clic, validación, error de conexión y conservación de campos |
| `php vendor/bin/pint` en los archivos modificados y `php vendor/bin/pint --test` | Correctos; comprobación final sobre todo el proyecto |
| `npm.cmd run build` | Correcto, 62 módulos, recursos compilados disponibles |
| `php artisan view:clear` y `php artisan view:cache` | Correctos |
| `php artisan route:list --path=huespedes --except-vendor` | 14 rutas, con nueva alta protegida por permisos explícitos |
| `node --check tests/browser/interfaz-cec.mjs` | Correcto |
| `php tests/Support/probar_interfaz.php` con entorno de pruebas explícito | **11 comprobaciones agrupadas correctas en Edge**, 11 capturas, nueva reservación con huésped sin identidad y persistencia verificada |

Capturas de Inicio, alta móvil y ficha de reservación revisadas. Se verificó búsqueda desde su botón, teclado y creación/selección del mismo registro conservando campos. Evidencias y límites: `PRUEBAS_INTERFAZ.md`. No se atribuye CSRF al navegador en testing: sí se probó adicionalmente con middleware real en HTTP.

La siguiente fase funcional sigue siendo **6, Servicios adicionales**. No se añaden métricas del panel ni módulos sin implementar. Los demás recorridos visuales pendientes se mantienen en el informe; no hay preguntas necesarias para este ajuste.

Cierre del ajuste: recorrido Edge repetido tras el texto Sin registrar, correcto en 11 comprobaciones; cero esquemas temporales restantes. Evidencias finales en storage/app/private/pruebas-interfaz/81d0664383fad14a/.

## Investigación y plan de calendario e iCalendar — 2026-10-01

Preparado [PLAN_CALENDARIO_ICAL.md](PLAN_CALENDARIO_ICAL.md): fuentes oficiales, límites del intercambio, tablas/servicios propuestos, disponibilidad centralizada, seguridad SSRF, exportación sin datos personales, sincronización programada, calendario visual y piloto bidireccional.

El usuario confirmó bloqueo automático de eventos incompletos, liberación después de comprobación automática y ambos sentidos. Las precisiones y casos pendientes figuran en DECISIONES.md y en el plan. No se presupone que un evento suministre huésped, importes o reglas fiscales; no se crean entidades paralelas de cliente o reservación.

Revisión realizada sobre modelos, ServicioDisponibilidad, bloqueo de cabañas, reservaciones, permisos, dependencias, colas y programador existentes. La documentación de Airbnb confirma actualización cada tres horas; Booking mantiene pendiente comprobación de frecuencia/eligibilidad porque su ayuda devolvió 403. El plan propone verificaciones con fixtures y red sustituida, MySQL separado y un piloto coordinado después.

Entrega exclusivamente documental: sin código funcional, dependencias nuevas, migraciones, conexión a feeds privados ni cambios en datos operativos. No se repitieron pruebas PHP, JavaScript o build por estos cambios de Markdown; las pruebas iCalendar se ejecutarán al implementar. Se comprobó la presencia y coherencia de los tres documentos de seguimiento.

La próxima fase funcional sigue siendo **6, Servicios adicionales**. La implementación de calendario corresponde a la **fase 9**, con las dependencias ya establecidas.
