# Plan de cambio definitivo a MySQL

Fecha: 2026-10-01. Decisión del usuario: utilizar MySQL en desarrollo y verificaciones futuras, conservando datos y trabajo existentes.

## Punto de partida

La aplicación ya conecta a MySQL 8.0.46, base `sistema_cabanas`. Las doce migraciones se ejecutaron y los datos de SQLite se trasladaron en la intervención anterior. El admin conserva sus permisos y contraseña. No corresponde repetir la transferencia ni recrear la base del sistema.

El inventario detectó configuración predeterminada de SQLite en `.env.example`, `config/database.php`, `config/queue.php` y PHPUnit; pruebas con conexiones en memoria, un proceso auxiliar y una prueba de concurrencia con archivos SQLite; una rama específica de bloqueo en `ServicioBloqueoCabanas`; creación automática del archivo SQLite en Composer; instrucciones de instalación y decisiones de pruebas pendientes de actualizar.

## Acciones y dependencias

| Paso | Archivos o responsabilidad | Verificación | Estado |
| --- | --- | --- | --- |
| 1 | Confirmar destino, preservar credenciales, datos y clave; inventariar dependencias | Conexión MySQL, estado de migraciones y datos administrativos sin exponer secretos | Terminado: destino y admin verificados |
| 2 | Alinear `.env`, `.env.example`, configuración de base/colas, Composer e ignorados | Predeterminados MySQL; sin creación automática de SQLite ni secretos versionados | Terminado |
| 3 | Cambiar PHPUnit y soporte de pruebas a MySQL | Bases nuevas con nombre aleatorio reservado, fuera de `sistema_cabanas`; migrar una vez y revertir transacciones entre pruebas | Terminado después de 2 |
| 4 | Adaptar migración de usuarios y concurrencia multiproceso | Pruebas con esquemas propios MySQL y dos procesos sobre una misma cabaña | Terminado después de 3 |
| 5 | Usar bloqueo MySQL y verificar integridad, precios y buscador | Suite completa en MySQL; claves foráneas, decimales, autorizaciones, estados y selección compartida | Terminado después de 3–4 |
| 6 | Actualizar instalación, instrucciones de agentes y documentos de seguimiento | Indicaciones actuales coherentes; resultados históricos conservados e identificados | Terminado después de verificar 5 |
| 7 | Cerrar con comprobaciones reales | Pint, JavaScript, build, vistas y datos reales conservados; registrar limitaciones | Terminado después de 5–6 |

## Aislamiento y conservación

- PHPUnit fuerza `testing`, MySQL y el prefijo reservado `sistema_cabanas_pruebas`. El soporte crea un esquema nuevo con sufijo aleatorio; rechaza el nombre operativo y nunca reutiliza esquemas existentes.
- Las pruebas comunes usan transacciones que se revierten. Las pruebas que requieren DDL o procesos independientes usan esquemas adicionales creados por esa ejecución. Solo se retiran esos esquemas con datos ficticios; no se ejecutan `migrate:fresh`, `db:wipe` ni reinicios de bases existentes.
- Las credenciales se cargan del entorno privado, nunca se escriben en XML, Markdown o argumentos del proceso auxiliar. El usuario MySQL de pruebas necesita permisos para crear y retirar exclusivamente los esquemas de pruebas.
- El archivo SQLite anterior permanece conservado y excluido de Git. No es una conexión activa ni un destino de pruebas.
- Se conservan las migraciones ejecutadas, incluida la corrección histórica exclusiva de SQLite, que no altera MySQL. Un defecto de esquema descubierto en MySQL se corrige mediante una migración nueva.
- Los informes anteriores mantienen sus resultados reales de SQLite. No se sustituyen retrospectivamente por resultados de MySQL.

## Criterios de cierre

Configuración y ejemplos usan MySQL; ninguna prueba depende de SQLite; la suite completa y la concurrencia multiproceso pasan en MySQL; los esquemas temporales de la ejecución se retiran; los datos operativos permanecen conservados; instalación y seguimiento reflejan comandos y resultados reales. Las fases funcionales siguen en el mismo orden; este cambio no implementa Servicios ni amplía reglas del negocio.

## Dudas y límites

El motor y la conservación de datos ya fueron decididos por el usuario. No hay preguntas de negocio nuevas para este cambio. Si el acceso no permite crear esquemas aislados, se documentará el bloqueo sin usar la base operativa para pruebas. La revisión visual depende de disponer de un navegador automatizable.

## Ejecución y resultados

Cambio implementado y verificado. Se alinearon `.env`, `.env.example`, `.gitignore`, `config/database.php`, `config/queue.php`, Composer, PHPUnit y todo el soporte de pruebas. No se instalaron dependencias ni se modificaron los archivos de bloqueo. La conexión SQLite y su rama de bloqueo se retiraron de la aplicación; sus migraciones históricas y archivo anterior se conservaron.

El inventario y las pruebas detectaron dos ajustes necesarios:

1. La comunicación de procesos con entrada dinámica de Symfony no llegó a completar la barrera de sincronización en Windows. Se adaptó el proceso de concurrencia a canales de `proc_open`, con plazos de espera y cierre de los procesos, antes de repetir las pruebas.
2. MySQL con `utf8mb4_unicode_ci` igualaba `DOC-É001` y `DOC-E001`, rechazando el segundo documento. Una prueba nueva reprodujo el defecto. La migración `2026_10_02_031921_conservar_comparacion_exacta_de_identidad_en_mysql.php` cambia solo `huespedes.identidad_busqueda` a `utf8mb4_bin`, conservando el índice único, originales y normalización existente. Los nombres mantienen su búsqueda sin tildes. Las pruebas de búsqueda y duplicados pasaron tras corregirlo. El rollback puede ser rechazado si luego existen documentos que la intercalación anterior igualaría; no se ejecutó rollback ni se borran identidades para forzarlo.

| Comando o comprobación | Resultado real |
| --- | --- |
| Inspección y validación de destino operativo | MySQL, `local`, `sistema_cabanas`; admin y datos previos conservados |
| Primer grupo de migración/precios/autorización en MySQL | **32 pruebas, 256 aserciones**, correctas |
| Primer grupo de aislamiento/búsqueda/concurrencia | 24 de 25 correctas; tiempo de espera en la comunicación de procesos de Windows, corregido después |
| Prueba de concurrencia tras adaptar canales | **Una prueba, 13 aserciones**, correcta; una confirmación, un rechazo y una sola auditoría |
| Primera suite completa en MySQL | **332 pruebas, 1797 aserciones**, correctas, antes de añadir el caso de documentos con tildes |
| Caso nuevo de documentos con letras distintas | Detectó error 1062 de duplicado indebido; se añadió la migración correctiva sin editar migraciones ejecutadas |
| Búsqueda, administración de huéspedes y concurrencia tras corregir la intercalación | **70 pruebas, 379 aserciones**, correctas |
| `php artisan test --compact`, suite final tras corrección y formato | **333 pruebas, 1800 aserciones**, correctas en MySQL; duración aproximada 74 segundos |
| `php vendor/bin/pint` sobre archivos modificados y `php vendor/bin/pint --test` | Formato aplicado y comprobación final correcta |
| `node --test tests/js/buscador-huesped.test.js` | **10 pruebas correctas** |
| `npm.cmd run build` | Correcto; Vite 8.3.1, 62 módulos |
| `php artisan view:cache` | Correcto |
| `php C:/laragon/bin/composer/composer.phar validate --no-check-publish` | `composer.json` válido y archivo de bloqueo coherente |
| `php artisan migrate --no-interaction` sobre destino comprobado y `php artisan migrate:status` | Corrección de identidad aplicada: **13 migraciones ejecutadas**, ninguna pendiente |
| Consulta posterior de conteos, Gates e intercalación | Un admin activo, 72 permisos, un registro de auditoría, una sesión y cuatro entradas de caché; cero cabañas/huéspedes/reservaciones; identidad `utf8mb4_bin` |
| Consulta posterior de esquemas de pruebas | **Cero esquemas temporales restantes** |
| `git check-ignore` | `.env`, `.env.testing`, volcados SQL y archivo SQLite anterior excluidos de Git |

La diferencia respecto de los recuentos anteriores de aserciones obedece a migrar una vez por ejecución en lugar de hacerlo en cada prueba; se conservaron las pruebas de negocio y se añadieron seis casos de protección del destino y uno de identidad.

No se alteraron la contraseña del admin, los datos del sistema ni otras bases preexistentes. No se repitió la transferencia. No hay preguntas pendientes para el cambio de motor. La revisión visual sigue pendiente porque no hay un navegador automatizable disponible; las pruebas HTTP, JavaScript y compilación no constituyen esa revisión. Facturación, pagos e iCalendar siguen pendientes de sus fases, con sus futuras pruebas en MySQL.

## Recreación solicitada por el usuario — 2026-10-01

El usuario informó que eliminó la base MySQL para ejecutar nuevamente las migraciones y pidió reconstruirla. Se comprobó `local`, conexión MySQL y destino `sistema_cabanas`, inexistente y sin tablas. Se creó únicamente esa base con utf8mb4/utf8mb4_unicode_ci; no se eliminaron ni alteraron otras bases.

Comandos ejecutados: `php artisan config:clear`, `php artisan migrate --no-interaction`, `php artisan db:seed --class=PermisosSeeder --no-interaction` y `php artisan migrate:status`. Resultado: **13 migraciones aplicadas**, ninguna pendiente; rol Administrador y **72 permisos** preparados.

El usuario autorizó después recuperar **únicamente el admin anterior** desde el archivo conservado. La recuperación puntual se realizó en una transacción, conservando ID, datos de la cuenta y hash de contraseña; asignando el rol Administrador del destino y registrando `administrador_restaurado_por_consola` en auditoría. No se reutilizó el token de recordar ni se importaron sesiones, caché, datos de negocio o auditoría anterior. El archivo conservado se leyó en modo de solo lectura y quedó intacto. Esta autorización puntual no habilita repetir una importación general.

Comprobaciones posteriores: el proveedor de autenticación encuentra al admin activo; sus **72 permisos** están autorizados y el hash coincide con el original, sin imprimirlo. Un usuario, un rol, 72 permisos, una asignación de rol y un registro de auditoría. Cero cabañas, huéspedes, reservaciones, sesiones y entradas de caché. Identidad `utf8mb4_bin` y subtotal `DECIMAL(16,2)` correctos.

No se modificó código, claves, credenciales ni migraciones. No se ejecutaron `migrate:fresh`, `db:wipe` o rollback. Los scripts puntuales se retiraron al terminar. No se repitió la suite: la verificación de este paso fue la aplicación real de las migraciones y las comprobaciones anteriores; la suite MySQL de 333 pruebas corresponde al cambio de motor previo.

El esquema implementado cubre las fases 1 a 5 y las tablas de infraestructura. Servicios, facturación, pagos y las demás fases siguen pendientes; esta reconstrucción no crea sus funcionalidades.
