# Inicio local y verificaciones

La fase 1 proporciona acceso de personal e inicio autenticado. La fase 2 incorpora usuarios, roles, permisos y consulta de auditoría. La fase 3 incorpora el catálogo de cabañas. La fase 4 incorpora huéspedes y el buscador compartido; la fase 5 incorpora reservaciones, disponibilidad y tarifas HNL/USD independientes. Los módulos de negocio siguen el orden de `PLAN_IMPLEMENTACION.md`.

## Entorno inspeccionado

Laravel 13.34.0, PHP 8.3.30, MySQL 8.0.46, Node 24.16.0, npm 11.13.0, Bootstrap 5.3.8 y Vite 8.3.1. Se conservaron los archivos de bloqueo. No se cambió la versión principal de Laravel.

En esta instalación de Windows se utiliza `npm.cmd` porque PowerShell bloquea `npm.ps1`. Composer está disponible mediante `php C:/laragon/bin/composer/composer.phar`.

## Configuración MySQL

El `.env` existente ya está configurado. En una instalación nueva, completar las credenciales privadas y crear previamente la base destinada a esa instalación:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_cabanas
DB_USERNAME=
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Los campos vacíos son marcadores, no credenciales predeterminadas. Se requiere la extensión PHP `pdo_mysql`. La configuración declara InnoDB y modo estricto. Sesiones, caché y colas usan la conexión MySQL configurada; no necesitan bases SQLite adicionales.

Después de editar el entorno, ejecutar `php artisan config:clear`. Conservar `APP_KEY`, contraseña del admin y archivos de bloqueo. `composer setup` ejecuta migraciones y generación de clave: no usarlo sobre esta instalación existente. El evento de Composer para un proyecto nuevo ya no crea un archivo SQLite ni migra automáticamente; el destino se verifica primero.

El detalle de acciones y resultados está en `PLAN_CAMBIO_MYSQL.md`. Las migraciones ejecutadas se conservan. Una migración histórica menciona SQLite para corregir su precisión monetaria; no hace cambios en MySQL y no habilita ese motor.

## Arranque

1. Conservar `.env` y su clave existentes. En una instalación nueva, copiar `.env.example`, configurar la base y generar una clave mediante `php artisan key:generate`. Nunca regenerar una clave existente sin evaluar los datos cifrados.
2. Para reproducir dependencias, ejecutar `composer install` (o el lanzador PHP anterior) y `npm.cmd ci --ignore-scripts`. No usar `composer update` ni el script `composer setup` sobre una base sin verificar: este último ejecuta migraciones automáticamente.
3. Verificar `php artisan env`, `php artisan db:show` y `php artisan migrate:status` antes de `php artisan migrate`. No usar comandos de borrado o reinicio de base.
4. Compilar con `npm.cmd run build`.
5. La cuenta `admin` solicitada ya existe en esta instalación local. Para otras cuentas sin privilegios puede utilizarse `php artisan usuarios:crear`, que solicita nombre, usuario, correo y contraseña oculta con confirmación. Requiere al menos 12 caracteres y como máximo 72 bytes por el límite de bcrypt. No asigna roles ni permisos administrativos.
6. Iniciar con `php artisan serve` y abrir `/iniciar-sesion` en la dirección local indicada. También puede utilizarse el sitio configurado en Laragon. Para desarrollo de recursos, ejecutar `npm.cmd run dev` en otra terminal.

El acceso se limita después de cinco fallos por combinación de usuario e IP durante un minuto. Las cuentas inactivas no pueden acceder y sus sesiones se revocan en la siguiente solicitud protegida. No existe registro público ni contraseña predeterminada.

La instalación actual usa `sistema_cabanas`, recreada a petición del usuario con las 13 migraciones. Se recuperó únicamente el admin anterior con su misma contraseña y 72 permisos; el resto de los datos quedó vacío. No repetir esa transferencia ni recrear la base. El archivo SQLite anterior permanece conservado y excluido de Git; no se utiliza para la aplicación ni para las pruebas.

## Administración de usuarios y acceso

El menú muestra Usuarios, Roles, Permisos y Auditoría según los permisos efectivos del usuario. Las rutas del servidor exigen el permiso de cada operación, incluso si se invocan directamente.

- Usuarios: alta, edición de datos, activación/desactivación, eliminación lógica, restablecimiento de contraseña y asignaciones de roles/permisos mediante formularios separados.
- Roles: crear, editar, consultar sus permisos y eliminar si no está asignado al personal. El rol Administrador no se renombra, elimina ni pierde permisos.
- Permisos: catálogo de códigos del sistema y permisos personalizados. Los códigos del sistema están protegidos. Añadir un código no implementa una nueva función ni cambia la autorización de una ruta existente.
- Auditoría: listado paginado, filtros por módulo/acción/usuario/fechas y detalle. No tiene acciones de edición o borrado.

Cada cuenta nueva carece de privilegios hasta asignarle un rol o permisos directos. Los permisos directos y los heredados por roles se suman. Solo se pueden conceder permisos que el operador posee. Las cuentas con rol Administrador, incluso inactivas, solo son administradas por administradores. Debe permanecer un administrador activo.

La semilla `php artisan db:seed --class=PermisosSeeder` prepara el catálogo y el rol Administrador de forma repetible, sin crear cuentas ni contraseñas. Tras nuevas migraciones, ejecutarla antes de usar la administración. El comando `php artisan usuarios:crear-administrador nombre_usuario` crea una cuenta mediante contraseña oculta y asigna ese rol. No reemplaza una cuenta existente ni inventa un correo.

El correo ahora es opcional en la administración web. No se habilitó recuperación por correo. La cuenta administradora solicitada se creó con una excepción explícita para la longitud de su clave inicial; los formularios generales mantienen la política de 12 caracteres. Su contraseña no se incluye en esta documentación.

## Verificación automatizada

```text
php artisan test
php vendor/bin/pint --test
npm.cmd run build
php artisan view:cache
php artisan route:list --except-vendor
```

PHPUnit fuerza `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_URL` vacío y el prefijo reservado `sistema_cabanas_pruebas`. `Tests\Support\BaseDatosMySql` crea un esquema nuevo con sufijo aleatorio y verifica que no exista antes de crearlo. Las migraciones se aplican una vez por ejecución y las pruebas comunes revierten sus transacciones. Las pruebas de DDL y concurrencia utilizan esquemas nuevos propios. Al terminar solo se retiran los esquemas creados por esa ejecución. No se reutiliza ni vacía `sistema_cabanas`, ni se utilizan `RefreshDatabase`, `migrate:fresh` o `db:wipe`. El cliente HTTP de Laravel rechaza llamadas externas no sustituidas.

Las credenciales se cargan del `.env` privado. Si se requiere un servidor o usuario diferente para pruebas, usar un `.env.testing` privado con host, puerto, usuario, contraseña y clave de aplicación; PHPUnit fuerza el destino seguro igualmente. El acceso de pruebas debe poder crear y retirar esquemas `sistema_cabanas_pruebas_*`. No incluir credenciales en XML, argumentos ni documentación. El servidor de pruebas debe utilizar MySQL 8.0 o posterior e InnoDB, como el entorno verificado.

## Antes de desplegar

Configurar dominio, HTTPS, base de datos, correo, `APP_ENV=production` y `APP_DEBUG=false`. Las cookies de sesión son HttpOnly y SameSite=Lax; Secure se activa por defecto en producción y admite configuración explícita. Mantener la clave y credenciales fuera del repositorio. El motor aprobado es MySQL. Los datos del establecimiento y las reglas de negocio aún pendientes se consultan en `DECISIONES.md`. Verificar copias de seguridad y acceso del entorno destino antes de aplicar cambios de esquema.

La migración inversa de usuarios elimina los campos añadidos; no utilizar rollback sobre cuentas reales como una operación sin pérdida. Preferir migraciones correctivas hacia adelante.
## Uso del catálogo de cabañas

Después de iniciar sesión, abre **Cabañas**. El administrador existente ya dispone de los permisos del módulo.

- **Nueva cabaña** solicita nombre, capacidades, tarifas, estado inicial y activación. Descripción, notas e imagen son opcionales. Introduce tarifas reales; no hay importes precargados.
- El listado busca por nombre y filtra estado o activación. **Ver** muestra los datos; **Editar datos** modifica el catálogo.
- **Guardar estado y activación** permite al personal asignar disponible, ocupada, reservada, mantenimiento o bloqueada con `cabanas.cambiar_estado`. Estas modificaciones quedan auditadas.
- **Eliminar cabaña** la oculta conservando sus datos. **Ver eliminadas → Restaurar** recupera el mismo registro con su estado anterior; requiere `cabanas.restaurar`.
- La imagen admite JPEG, PNG o WebP de hasta 2 MiB y 3000 × 3000 píxeles. Se conserva en `storage/app/cabanas`, fuera del acceso público; no requiere `storage:link`. Su consulta requiere autenticación y `cabanas.ver`.

La disponibilidad por fechas ya se consulta mediante el módulo de reservaciones y ServicioDisponibilidad. El estado operativo actual no confirma que un intervalo esté libre. El sistema es interno: no se ofrece una reserva pública a usuarios externos. La sincronización iCalendar se construirá en su fase correspondiente.

## Huéspedes y búsqueda compartida

- **Huéspedes → Nuevo huésped** requiere nombre completo y teléfono; la identidad es opcional. El teléfono admite código internacional y separadores; la identidad admite letras/números, espacios y guiones. No se presupone un formato nacional. Se conservan originales y ceros.
- Cuando se registra, la identidad no se puede repetir, tampoco cambiando mayúsculas, espacios o guiones ni cuando la ficha anterior está eliminada. El teléfono puede compartirse entre personas.
- Nacionalidad, VIP, notas e incidentes se administran en la ficha. Activación, lista negra y frecuente tienen acciones y permisos independientes. El historial administrativo se muestra con `auditoria.ver` y no copia datos personales en sus valores auditados.
- **Buscar cliente** consulta nombre, teléfono o identidad desde tres letras/números; devuelve hasta 20 coincidencias. Flechas arriba/abajo recorren resultados, Enter selecciona y Escape cierra la lista. No hay selección automática.
- Las personas inactivas o en lista negra pueden seleccionarse con advertencia. Los eliminados no aparecen. Quienes pueden ver o buscar huéspedes acceden a la identidad completa, conforme a la decisión del usuario.
- **Nueva reservación → Nuevo huésped → Crear y seleccionar huésped** registra nombre y teléfono, con identidad opcional, sin cambiar de pantalla. Selecciona el registro recién creado y conserva cabaña, fechas, moneda, cantidad y notas. Requiere `huespedes.crear` además de `huespedes.buscar` y el permiso de la reservación. Busca primero para reutilizar una ficha existente.
- En el buscador independiente, si no hay coincidencias y se tiene permiso de crear, el alta se abre en otra pestaña. Regresa al formulario original, repite la búsqueda y selecciona expresamente el nuevo registro; los otros campos del formulario original permanecen intactos.
- **Ver eliminados → Restaurar** recupera la misma ficha y sus condiciones; no crea otra persona.

El componente `<x-buscador-huesped />` usa `ServicioBusquedaHuespedes` y conserva `huesped_id`. Las futuras operaciones deberán autorizar su permiso y llamar a `ServicioSeleccionHuesped::validar` dentro de su transacción; además comprobarán la correspondencia huésped/reservación/factura. La búsqueda no registra dinero ni sustituye la factura obligatoria de un pago. La integración en reservaciones y el historial de estancias ya se implementaron en fase 5; facturas y pagos se integrarán en fases 7 y 8.

Pruebas del componente: `node --test tests/js/buscador-huesped.test.js`, sin paquetes adicionales. Son pruebas con un sustituto mínimo del DOM, no una revisión visual en navegador.

## Inicio y recursos de interfaz

Inicio muestra tarjetas para los módulos implementados que la cuenta puede consultar, además del acceso a Nueva reservación con sus permisos. Cada tarjeta enlaza a la pantalla real; no incorpora métricas ni accesos a módulos todavía pendientes.

Para probar con `php artisan serve`, los recursos pueden servirse desde el build generado por `npm.cmd run build`, sin mantener Vite abierto. Si un proceso de Vite terminó de forma abrupta y dejó `public/hot`, Laravel seguirá solicitando sus recursos al servidor de desarrollo. Comprueba primero que ese servidor ya no responde; con el build disponible, retira únicamente ese marcador temporal y recarga la página. No borrar configuraciones ni datos para resolver el fallo.

Pruebas de interfaz y evidencias: `PRUEBAS_INTERFAZ.md`. El comando de navegador usa datos ficticios y MySQL separado.
