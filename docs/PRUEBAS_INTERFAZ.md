# Pruebas de interfaz — 2026-10-01

## Corrección del buscador y alta dentro de reservación — 2026-10-01

Solicitud implementada: buscador operativo, huésped nuevo desde la misma reservación, nombre/teléfono obligatorios e identidad opcional, y tarjetas de acceso en Inicio según permisos.

Causa comprobada del fallo del entorno: `public/hot` apuntaba a `http://[::1]:5173`, sin conexión en ese puerto. Laravel estaba solicitando allí los recursos de interfaz. Se ejecutó el build de Vite, se retiró únicamente el marcador obsoleto tras verificar el servidor y se limpiaron/compilaron las vistas. El acceso del servidor real en 8000 y sus recursos compilados respondieron HTTP 200. La prueba anterior utilizaba un router aislado que forzaba el build y por eso no comprobó ese marcador del servidor del usuario.

Nueva ejecución correcta: Edge 154, **11 comprobaciones agrupadas y 11 capturas**, en `storage/app/private/pruebas-interfaz/9134deaae0b0df30/`. Incluye el recorrido anterior y ahora:

- Tarjetas de Inicio en escritorio y móvil; clic en Nueva reservación desde su tarjeta.
- Intento de alta con campos obligatorios vacíos sin crear ni seleccionar un huésped.
- Alta dentro de reservación con nombre/teléfono internacional y sin identidad, conservando fecha, notas y selección de cabaña/moneda.
- Selección del registro creado, consulta posterior mediante el botón Buscar y nueva selección explícita del mismo huésped.
- Reservación guardada con ese huesped_id y subtotal exacto HNL 135.75 por una noche. Persistencia final: dos huéspedes, dos reservaciones y una cabaña, en el esquema ficticio; identidad del huésped nuevo null.
- Sin excepciones JavaScript ni respuestas HTTP con error durante el recorrido.

Capturas revisadas visualmente:

- [Inicio con tarjetas](../storage/app/private/pruebas-interfaz/9134deaae0b0df30/03-inicio-escritorio.png)
- [Alta en el formulario móvil](../storage/app/private/pruebas-interfaz/9134deaae0b0df30/10-alta-huesped-movil.png)
- [Reservación con el huésped nuevo](../storage/app/private/pruebas-interfaz/81d0664383fad14a/11-reservacion-alta-rapida.png)

Comprobaciones adicionales: pruebas PHP específicas del alta y tarjetas **10 correctas, 80 aserciones**, incluyendo permisos separados, identidad duplicada, campos sensibles y CSRF real (419 sin token; 201 con X-CSRF-TOKEN válido). **13 pruebas JavaScript correctas**; `node --check`, Pint, build de Vite y compilación Blade correctos. Suite completa: **343 pruebas PHP correctas, 1884 aserciones**. Ajuste final de presentación Sin registrar verificado después con **8 pruebas específicas y 67 aserciones**. Detalle de ejecuciones en PLAN_IMPLEMENTACION.md.

El recorrido se repitió correctamente después del texto Sin registrar: evidencias finales en `storage/app/private/pruebas-interfaz/81d0664383fad14a/`. Cero esquemas temporales al terminar; datos operativos conservados. El recorrido de navegador sigue usando testing; la comprobación adicional de CSRF se ejecutó con middleware real mediante prueba HTTP aislada. No se crearon huéspedes ni reservaciones ficticios en la base del usuario, se cambiaron credenciales ni se ejecutaron migraciones. Las columnas de identidad ya admiten null.

## Primera verificación gráfica (histórica)

Resultado: recorrido real correcto en Microsoft Edge 154.0.4258.48, mediante clics, formularios y teclado. Diez comprobaciones agrupadas y ocho capturas. Se revisaron visualmente las capturas del buscador, la reservación confirmada y el listado móvil; también el acceso durante el diagnóstico inicial.

Se utilizó un navegador independiente en modo headless con perfil nuevo, escritorio de 1366 × 900 y viewport móvil de 390 × 844. No se controló la ventana del usuario. Bootstrap y JavaScript provienen del build de Vite existente, sin modificar `public/hot` ni el servidor del usuario.

## Resultados observados

| Recorrido | Resultado |
| --- | --- |
| Acceso | Contraseña incorrecta rechazada; acceso correcto, cierre de sesión y redirección al acceso al visitar Usuarios sin sesión |
| Navegación | Usuarios, Roles, Permisos y Auditoría abiertos mediante sus enlaces; encabezados renderizados |
| Cabaña | Alta con capacidades y tarifas ficticias HNL/USD independientes; registro visible en listado |
| Huésped | Alta con nombre, teléfono internacional e identidad; desactivación y lista negra desde su ficha |
| Buscador en reservación | `maria` encontró a María; teléfono e identidad sin separadores encontraron el mismo registro; selección explícita con teclado; notas y moneda conservadas |
| Advertencias | Huésped inactivo y en lista negra seleccionable, con ambas advertencias visibles |
| Reservación | Alta por confirmar; dos noches y tres personas: 2 × (123.45 + 12.30) = HNL 271.50 |
| Reprogramación y estados | Fechas modificadas conservando tarifa; confirmación y cancelación desde la ficha |
| Persistencia MySQL | Una sola cabaña, un solo huésped y una reservación cancelada con subtotal exacto 271.50 |
| Presentación y recursos | Acceso y listado de reservaciones sin desbordamiento horizontal de página a 390 px; ninguna excepción JavaScript ni respuesta HTTP con error en el recorrido |

Las cantidades son exclusivamente datos ficticios de prueba; no se incorporan como tarifas del establecimiento.

## Aislamiento y evidencias

`tests/Support/probar_interfaz.php` crea un esquema MySQL nuevo, con prefijo de pruebas y sufijo aleatorio, utilizando el soporte existente. Aplica las migraciones y permisos, genera credenciales ficticias aleatorias por stdin e inicia un servidor temporal en un puerto local independiente. El router rechaza destinos no aislados. Al finalizar retira únicamente el esquema y el perfil creados por la ejecución. No modifica cuentas ni registros de `sistema_cabanas`.

Ejecución correcta: `storage/app/private/pruebas-interfaz/823b45af0be9a4b6/`. Contiene `resultado.json`, log del servidor y las ocho capturas numeradas. Estos archivos privados quedan excluidos de Git por el `.gitignore` existente.

- [Buscador y advertencias](../storage/app/private/pruebas-interfaz/823b45af0be9a4b6/05-buscador-reservacion.png)
- [Reservación confirmada](../storage/app/private/pruebas-interfaz/823b45af0be9a4b6/06-reservacion-confirmada.png)
- [Listado móvil](../storage/app/private/pruebas-interfaz/823b45af0be9a4b6/08-reservaciones-movil.png)

Las primeras ejecuciones fallaron en la conexión de Edge y después en un clic del automatizador durante el desplazamiento de la página. Se ajustó el perfil de Edge y el desplazamiento instantáneo de los clics, y se eliminó la reutilización de selectores de botones. Los intentos fallidos conservan sus evidencias; no se atribuyen a un defecto del sistema. No se modificó código de la aplicación ni se instalaron dependencias.

El navegador de pruebas usa `--no-sandbox` para permitir su ejecución en este entorno, limitado al perfil temporal y datos ficticios; esta opción no configura el navegador del usuario.

## Comandos y repetición

Requiere Windows, Edge instalado en la ruta configurada en el script, Node 24, build de Vite existente y acceso MySQL con permisos para crear y retirar esquemas de pruebas. Si se modifican recursos, compilar antes con `npm.cmd run build`.

Ejecutar en una terminal PowerShell nueva; las variables no modifican `.env`:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='sistema_cabanas_pruebas'
$env:DB_URL=''
php tests/Support/probar_interfaz.php
```

Comprobaciones ejecutadas:

| Comando o consulta | Resultado |
| --- | --- |
| Comando de navegador anterior | Código 0, diez comprobaciones agrupadas correctas y persistencia verificada |
| `php vendor/bin/pint tests/Support/probar_interfaz.php tests/Support/servidor_interfaz.php` | Formato corregido en los dos nuevos archivos PHP |
| `php vendor/bin/pint --test tests/Support/probar_interfaz.php tests/Support/servidor_interfaz.php` | Correcto |
| `node --check tests/browser/interfaz-cec.mjs` | Correcto |
| Consulta de information_schema | Cero esquemas con el prefijo temporal al terminar |
| GET `http://127.0.0.1:8000/iniciar-sesion` | HTTP 200; servidor del usuario conservado |

No se repitió la suite PHP completa ni el build: no cambiaron funcionalidades ni recursos de la aplicación. Sus resultados previos se mantienen separados de esta prueba de navegador.

## Límites y pendientes

El recorrido cubre los flujos anteriores, no todos los formularios ni combinaciones. Quedan pendientes el alta de huésped en otra pestaña, administración de usuarios/roles mediante formularios, imágenes, restauraciones, entrada/salida, no presentado, conflictos visibles de disponibilidad y una reservación en USD. La emulación móvil verifica viewport y capturas, no un dispositivo físico ni gestos táctiles.

El servidor usa `APP_ENV=testing`: CSRF no queda validado por este recorrido, porque Laravel lo omite en ese entorno. Autorización, CSRF y reglas críticas mantienen sus pruebas PHP específicas. No se afirma cobertura visual de servicios, facturas, pagos o iCalendar todavía no implementados. La siguiente fase funcional sigue siendo Servicios adicionales.
