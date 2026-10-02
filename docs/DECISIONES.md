# Decisiones y preguntas pendientes

Fecha: 2026-09-29. Este registro no sustituye ni amplía la especificación.

## Plan de calendario e iCalendar — 2026-10-01

El usuario solicitó investigación y un plan, sin implementar todavía la integración. Plan detallado: [PLAN_CALENDARIO_ICAL.md](PLAN_CALENDARIO_ICAL.md), con fuentes oficiales, secuencia, seguridad, verificaciones y pendientes.

Decisiones expresamente confirmadas:

- Los eventos externos bloquean automáticamente las fechas aunque no traigan datos de huésped o precio; el personal completa después la reservación. No crear huéspedes ficticios ni importes inventados.
- Cuando desaparezca un evento, liberar tras comprobación automática. Fallos de conexión, descargas parciales o errores de formato no liberan fechas.
- Sincronización en ambos sentidos: Airbnb/Booking hacia CEC y disponibilidad de CEC hacia cada plataforma. La lectura externa requiere un enlace HTTPS accesible desde Internet.

Diseño propuesto, pendiente de implementación: fuentes y eventos de integración separados de la única entidad Reservacion; bloqueos consumidos por ServicioDisponibilidad bajo el mismo bloqueo transaccional de cabaña; enlaces de exportación anónimos, revocables y con UID estable; reconciliación idempotente y auditoría. Dos lecturas completas consecutivas para verificar ausencia y consultas locales cada 15 minutos son propuestas técnicas configurables, no políticas de los proveedores.

No se considera que liberar un bloqueo externo cancele automáticamente una estancia, factura o pago. Falta precisar la propagación de bajas y cambios cuando existe una reservación local vinculada, el rango de los estados manuales, el mapeo anuncio/cabaña, dominio HTTPS y conexiones cruzadas. El reenvío entre canales se condiciona al piloto de identificación de ecos y cancelaciones; no se promete evitar toda doble reserva con sincronización periódica.

Verificación de investigación: Airbnb documenta lecturas cada tres horas. El artículo de ayuda de Booking respondió 403; su intervalo exacto y elegibilidad de la cuenta no están verificados. No se consultaron feeds privados, instalaron dependencias, ejecutaron migraciones ni modificó código funcional. No se ejecutaron pruebas de integración todavía. La siguiente fase funcional continúa siendo 6, Servicios adicionales; este trabajo prepara la fase 9.

## Ajuste autorizado de reservaciones e inicio — 2026-10-01

La indicación posterior del usuario aprueba alta de huésped dentro del formulario de reservación, identidad opcional y tarjetas de acceso en Inicio. Sustituye la obligatoriedad anterior de identidad y la limitación inicial de alta en otra pestaña para reservaciones.

- Nombre y teléfono continúan obligatorios, con formato internacional. Identidad omitida o vacía se guarda como null; si se introduce, conserva validación, comparación y unicidad incluso en fichas eliminadas. El teléfono sigue sin ser único, porque puede ser compartido. No se inventan identificadores para datos faltantes.
- Se conserva una sola entidad Huesped. La creación es explícita por el botón Crear y seleccionar huésped, con `huespedes.crear` y `huespedes.buscar` en el servidor. Reutiliza GuardarHuespedRequest, ServicioHuespedes, transacción y auditoría existentes; devuelve exclusivamente datos de selección. La reservación usa ese mismo huesped_id y revalida el registro al guardar.
- El alta no navega fuera del formulario ni altera fechas, cabaña, moneda, cantidades o notas. Distingue validación y conexión, conserva entradas ante errores y bloquea doble clic mientras guarda. Sin identidad no se deduce equivalencia por teléfono o nombre; buscar y seleccionar una ficha existente continúa siendo explícito.
- Inicio utiliza tarjetas con permisos explícitos y rutas existentes. No se añaden indicadores del panel, reglas comerciales ni módulos ficticios.
- El buscador fallaba en el entorno abierto porque `public/hot` apuntaba a Vite sin conexión. Se compiló el frontend, se verificó nuevamente que Vite no respondía y se retiró solo ese marcador generado. El servidor en 8000 entrega el JavaScript/CSS compilados con HTTP 200.
- MySQL ya admite null en numero_identidad e identidad_busqueda. Se comprobó el esquema real; este ajuste no necesita migraciones ni modifica datos existentes.

Verificación: 343 pruebas PHP y 1884 aserciones, 13 pruebas JavaScript, Pint, build y Blade correctos. Ajuste final Sin registrar verificado con 8 pruebas específicas y 67 aserciones. Recorrido de Edge con 11 comprobaciones agrupadas correcto. Resultados finales y capturas en `PRUEBAS_INTERFAZ.md`; comandos de cierre en `PLAN_IMPLEMENTACION.md`. La siguiente fase funcional sigue siendo Servicios adicionales.

## Estado actual tras la recreación — 2026-10-01

El usuario eliminó la base MySQL y solicitó reconstruirla con las migraciones actuales. Se verificó que `sistema_cabanas` no existía, se creó y se ejecutaron las **13 migraciones** y `PermisosSeeder`, sin borrar otras bases ni modificar el esquema fuente.

Por respuesta expresa del usuario se recuperó **únicamente el admin anterior**, conservando su misma contraseña y asignando el rol Administrador con **72 permisos**. La restauración puntual quedó auditada. No se restauraron datos de negocio, sesiones, caché ni auditoría antigua; no se habilita una importación general. El archivo anterior permanece intacto.

Estado comprobado: un admin activo, un rol, 72 permisos y un registro de auditoría; cero cabañas, huéspedes, reservaciones, sesiones y caché. Todas las migraciones ejecutadas. El esquema funcional cubre las fases 1–5; la siguiente continúa siendo Servicios adicionales. Los recuentos de sesiones/caché de apartados anteriores son históricos y anteriores a la eliminación efectuada por el usuario.
## Decisiones resueltas

1. Conservar esta instalación Laravel 13 y ambos archivos de bloqueo. No crear otro proyecto. La indicación de crear Laravel limpio se satisface con la plantilla existente, conforme a AGENTS.md.
2. Blade + Bootstrap 5 y JavaScript nativo mediante Vite. Sin CDN para recursos principales. Reemplazar las vistas de Tailwind antes de quitar sus dependencias.
3. Usar `Usuario`/`usuarios`, `Cabana`/`cabanas`, `Huesped`/`huespedes` y `Reservacion`/`reservaciones`. Identificadores propios en español sin tildes ni ñ; textos visibles en español correcto.
4. Convención inicial, ampliada por la decisión posterior de fase 5: `pendiente`, `confirmada`, `hospedada`, `finalizada`, `cancelada`, `no_presentado`. La alternativa `check_in`/`check_out` del documento operativo queda descartada por la precisión de AGENTS.md.
5. Un solo servicio de disponibilidad y uno de precios; impuestos desde una única configuración, con valores históricos. No hay tarifas ni impuestos predeterminados inventados.
6. Autenticación de personal con nombre de usuario, contraseña y opción de recordar. Sin registro público. Usar guard de sesión nativo, regeneración de sesión, CSRF, limitación de intentos y rechazo de cuentas inactivas también en sesiones existentes.
7. Comando interactivo `usuarios:crear` con contraseña oculta y sin contraseña fija ni fallback de escritura visible. Mínimo técnico de 12 caracteres y máximo de 72 bytes por bcrypt. Crear una cuenta no concede permisos administrativos; la matriz se implementa en fase 2. No se creó una cuenta real durante la verificación.
8. Adaptar `users` mediante una nueva migración de renombrado, conservando filas y hashes. El nombre de acceso añadido será nullable para preservar posibles filas anteriores; nuevas cuentas lo exigirán. No editar migraciones base ya ejecutadas ni reiniciar la base.
9. Guardar contraseñas como hashes y ocultarlas al serializar. Conservar las interfaces de autenticación de Laravel mediante mapeo explícito. Excluir `contrasena` de inputs antiguos y evitar su recorte silencioso.
10. La auditoría mínima de autenticación se introduce en fase 1 como dependencia de seguridad; su consulta y administración protegida pertenecen a fase 2. Registrar eventos e identificadores necesarios, nunca credenciales o cuerpos completos de solicitudes.
11. **Decision inicial sustituida por el cambio definitivo a MySQL del 2026-10-01.** En las fases iniciales se usaron pruebas aisladas en SQLite en memoria, sin llamadas ni comunicaciones externas. Se usan migraciones sobre conexiones nuevas efímeras en lugar de `RefreshDatabase`/`migrate:fresh`, para respetar la prohibición de comandos de borrado. `TestCase` comprueba entorno y destino antes de migrar. Mantener la base local SQLite; revisar motor de despliegue y concurrencia antes de operaciones financieras/reservaciones.
12. No inicializar Git ni inventar historial previo. El directorio inspeccionado no contiene `.git`.

## Decisión vigente sobre el motor y las pruebas — 2026-10-01

Esta decisión posterior sustituye las indicaciones anteriores de usar SQLite: **MySQL en desarrollo, despliegue y todas las pruebas de base de datos**. La base operativa es `sistema_cabanas`, ya trasladada; no volver a importar ni recrear datos. Los resultados antiguos de SQLite se conservan como historial, sin atribuirlos a MySQL.

- Configuración y `.env.example` usan MySQL, utf8mb4 e InnoDB; sesiones, caché y colas mantienen la conexión configurada. Se retiró la conexión activa SQLite y la creación de su archivo en Composer. Credenciales y claves permanecen privadas.
- PHPUnit fuerza `testing`, MySQL, URL vacía y el prefijo `sistema_cabanas_pruebas`. El soporte genera esquemas nuevos con sufijo aleatorio y verifica que no existan. Migra una vez por ejecución, revierte transacciones entre pruebas y solo retira los esquemas creados por esa ejecución. DDL y concurrencia usan esquemas propios. No se utiliza `RefreshDatabase`, `migrate:fresh` ni `db:wipe`.
- La autorización del usuario para este cambio comprende adaptar el soporte de pruebas y la comprobación multiproceso; los datos de pruebas son ficticios y están separados de la base del sistema. El destino operativo, otras bases y los esquemas preexistentes se rechazan como destinos de creación de pruebas.
- `ServicioBloqueoCabanas` utiliza `FOR UPDATE` en una transacción MySQL, con los identificadores ordenados. Concurrencia real verificada desde dos procesos: una confirmación y un rechazo para la misma cabaña, con una sola auditoría.
- Se detectó que la intercalación predeterminada igualaba letras con/sin tilde en documentos de identidad. Una migración nueva aplica `utf8mb4_bin` a su columna comparable, preservando la comparación existente y el índice único. La normalización no cambia: mayúsculas y eliminación de separadores; ignorar tildes se mantiene solo para nombres. No se editaron las doce migraciones ya ejecutadas.
- Verificación final: **333 pruebas PHP, 1800 aserciones en MySQL**, diez pruebas JavaScript, Pint, Vite y compilación Blade correctos. Trece migraciones ejecutadas en la base operativa; admin activo con 72 permisos y datos previos conservados; cero esquemas temporales restantes.
- Revisión visual parcial realizada posteriormente en Edge; alcance y pendientes en `PRUEBAS_INTERFAZ.md`. Las futuras verificaciones de servicios, facturas, pagos, reembolsos e iCalendar se construirán sobre MySQL en sus fases, sin agregar reglas de negocio en este cambio.

Plan, archivos afectados, comandos, resultados y limitaciones: `PLAN_CAMBIO_MYSQL.md`. Instalación y credenciales de pruebas: `INSTALACION.md`.
## Esquema inicial y evolución prevista

### Decisiones de fase 2

- El usuario autorizó explícitamente crear la cuenta `admin` con la contraseña que indicó y privilegios de administrador. La cuenta se creó y verificó activa con 69 de 69 permisos. La contraseña se recibió de forma oculta y solo se almacenó el hash. Esta alta puntual exceptúa la longitud mínima general; no cambia las reglas de las demás cuentas.
- Rol `administrador` con todos los permisos explícitos del catálogo, incluida `huespedes.buscar`. Sin bypass global de autorización ni columna de rol en usuarios. Los otros roles se configuran sin privilegios predeterminados.
- Gates de Laravel y relaciones Eloquent mediante `rol_usuario`, `permiso_rol` y `permiso_usuario`, sin otro paquete ni mecanismo paralelo.
- Conservar al menos un administrador activo; proteger el rol Administrador y los códigos de permisos del sistema. Un operador no puede otorgar permisos que no posee ni administrar cuentas de administradores sin ser administrador.
- Eliminación lógica de usuarios para conservar auditoría; roles asignados y permisos personalizados en uso no se eliminan. La auditoría se consulta por permiso y no tiene operaciones de modificación o borrado.
- El correo pasa a ser opcional: no se proporcionó uno para la cuenta solicitada y no se inventará. El acceso usa nombre de usuario; no existe recuperación por correo en esta fase.
- No se ejecuta borrado automático de auditoría mientras su retención permanezca pendiente.

- Fase 1: `usuarios` (nombre, usuario de acceso único, correo, hash de contraseña, activo, token de recuerdo y timestamps), `registros_auditoria` (usuario nullable, módulo, acción, IP y agente de usuario limitado). Conservar tablas técnicas de sesiones, caché, colas y tokens del framework.
- Fase 2: `roles`, `permisos`, `rol_usuario`, `permiso_rol`, `permiso_usuario`; un único mecanismo coherente de permisos, sin `usuarios.rol_id` paralelo. Completar auditoría sin secretos.
- Fases 3–6: `cabanas`, `reglas_precio_cabana` si las reglas aprobadas las requieren, `huespedes`, `reservaciones`, `servicios`, `reservacion_servicio`. Claves foráneas y copias históricas de importes aplicados.
- Fases 7–8: `facturas`, `detalles_factura`, `pagos`, `reembolsos`; pagos siempre asociados a factura, reembolsos separados y transaccionales.
- Fases 9–11: `eventos_calendario`, `configuraciones`; relaciones con cabaña/reservación según función. Reportes y panel consumen estas fuentes.
- No crear tablas de clientes, habitaciones, pisos, edificios, limpieza, reservas paralelas ni movimientos genéricos.

## Pendientes que requieren reglas del negocio

No bloquean la autenticación base. Consultar antes de implementar la parte afectada, sin asumir el comportamiento defectuoso del legado.

| Tema | Decisión pendiente | Fase afectada |
| --- | --- | --- |
| Roles | Administrador resuelto. Faltan los roles adicionales del personal y permisos de cada uno. Identidad visible a quienes tienen huespedes.ver o huespedes.buscar, según decisión del usuario | Antes de asignar esos roles |
| Auditoría | Retención y alcance de datos personales | 2; no automatizar borrado |
| Huéspedes | Resuelto por indicación posterior: nombre y teléfono obligatorios, identidad opcional; teléfono internacional, identidad alfanumérica sin formato nacional fijo; inactivos/lista negra seleccionables con advertencia | Aplicar en 4–8 |
| Operación | Resuelto en fase 5: pendiente/por confirmar no bloquean; horarios 14:00/11:00; excepciones autorizadas; no presentado desde salida prevista | Integrar en 9 |
| Precios | Cálculo por noche y persona adicional resuelto, HNL/USD independientes y elección de tarifa al reagendar. Faltan cifras reales que introducirá el personal y descuentos/reglas fiscales antes de facturación | 6–7 |
| Facturación | Monedas HNL/USD resueltas; tratamiento fiscal, tasas reales y reglas de emisión/anulación pendientes | 7 |
| Pagos | Métodos habilitados y condiciones de reembolso | 8 |
| Integraciones | Bloqueo automático, bajas comprobadas y ambos sentidos aprobados. Pendientes mapeo, rangos manuales, reservaciones vinculadas y topología; ver PLAN_CALENDARIO_ICAL.md | 9 |
| Despliegue | Motor resuelto: MySQL. Pendientes dominio HTTPS y operación de programador/worker para iCalendar | Antes de desplegar |

## Decisiones de fase 3

- Catálogo `Cabana`/`cabanas` con capacidades positivas y máxima igual o superior a la base. Tarifas obligatorias introducidas por el operador, sin valores iniciales inventados, importes negativos, redondeo silencioso ni cálculos fiscales. Almacenamiento decimal de dos posiciones; moneda y reglas de cálculo siguen pendientes antes de reservaciones/facturas.
- Edición de datos con `cabanas.editar`; estado y activación mediante una operación separada con `cabanas.cambiar_estado`. El alta define explícitamente su estado inicial y activación bajo `cabanas.crear`.
- El usuario confirmó que el personal también asigna manualmente ocupada y reservada. Los cinco estados operativos están habilitados con permiso y auditoría. Confirmó además que el sistema es interno: solo el personal gestiona reservaciones, junto con las actualizaciones mediante iCalendar; no se construirá un flujo público de reservas. La precedencia ante conflictos de sincronización se definirá al implementar iCalendar.
- Consulta de disponibilidad por fechas y `ServicioDisponibilidad` se implementarán juntos en fase 5, según el orden del documento constructor. No se presenta el estado operativo como disponibilidad por fechas.
- Imágenes JPEG, PNG o WebP privadas, hasta 2 MiB y 3000 × 3000 píxeles, nombres generados y acceso autenticado con `cabanas.ver`. No se exponen rutas de almacenamiento ni se admiten SVG. Se conservan imágenes reemplazadas junto con la referencia auditada; no hay purga automática.
- Eliminación lógica y restauración conservan identificador, creador, tarifas e imagen. En fase 5 se deberán añadir las restricciones que correspondan por reservaciones existentes antes de habilitar ese módulo.

## Decisiones de fase 4

- Se volvió a inspeccionar la aplicación existente. Ahora existe Git, sin archivos de aplicación registrados todavía; no se inicializó ni se alteró su historial.
- Se conserva una sola entidad Huesped. El nombre completo se almacena en un único campo nombre, sin duplicar nombres completos calculados; teléfono e identidad son cadenas originales con columnas derivadas para búsqueda.
- Normalización portable para SQLite y otros motores: nombres sin tildes ni diferencias de mayúsculas; teléfono sin separadores de formato, identidad sin espacios/guiones. Los símbolos de búsqueda se escapan como literales. No se deducen prefijos internacionales.
- Consulta autenticada con huespedes.buscar, mínimo tres caracteres útiles, orden de coincidencias exactas y máximo 20 resultados. El componente compartido invalida selecciones al editar texto y descarta respuestas antiguas.
- Decisión inicial sustituida el 2026-10-01: el usuario exigió nombre, teléfono e identidad para altas/ediciones; ahora exige nombre y teléfono, con identidad opcional. El teléfono conserva su código de país y no es único. La identidad mantiene la unicidad de la especificación, incluso en eliminados, sobre su representación comparable en mayúsculas sin espacios/guiones, con restricción de base de datos.
- La identidad completa se muestra con huespedes.ver en la ficha/listado y con huespedes.buscar en el buscador. No se otorga ninguno de esos permisos mediante una consulta.
- Las columnas de teléfono/identidad admiten nulos para que la consulta pueda manejar registros incompletos conforme a 86.3; las altas y ediciones HTTP inicialmente exigían los tres campos; la regla vigente admite identidad opcional. No se habilitó importación ni se precargaron personas.
- Respuesta recibida: inactivos y lista negra se muestran con advertencia y pueden seleccionarse. Esto no cambia sus indicadores ni omite la autorización y coherencia del documento de destino.
- El historial de operaciones financieras y estancias se conectará al construir reservaciones, facturas y pagos; en esta fase solo hay historial auditable de administración de la ficha.
- VIP se administra con la edición de ficha; frecuente, activación y lista negra tienen sus permisos y operaciones específicos. Las altas crean fichas activas, no frecuentes y fuera de lista negra; no conceden permisos.
- La auditoría registra identificadores, condiciones y nombres de campos modificados; excluye los valores de nombre, documento, teléfono y notas privadas. El historial de la ficha respeta auditoria.ver.
- El alta desde el buscador abre el formulario existente en otra pestaña con noopener. La operación de origen conserva sus campos en memoria; al volver se busca y selecciona explícitamente el nuevo registro. No se guardan borradores con datos personales en localStorage.
- La búsqueda JSON evita caché y tiene un límite técnico de 120 solicitudes/minuto por usuario. El listado comparte el mismo filtro normalizado, sin duplicar la búsqueda; filtrar por término también exige huespedes.buscar.

## Verificación al cierre de fase 3

Fases 1 a 3 implementadas. La suite completa final pasó con 151 pruebas y 1110 aserciones, incluidas 56 pruebas de Cabañas. Pint, Vite y compilación de Blade correctos. Ocho migraciones aplicadas en la base SQLite local tras verificar destino; cuenta administrativa conservada con 69 permisos. No se ejecutó revisión visual: no había navegador automatizable disponible. Tampoco se ensayó concurrencia multiproceso sobre el motor final. El detalle de comandos figura en `PLAN_IMPLEMENTACION.md`.

La página `/inicio` conserva el acceso a la cuenta y navegación según permisos; no representa el panel de indicadores. La auditoría tiene rutas autenticadas y protegidas por `auditoria.ver`, sin acceso público. Al cerrar la fase 3, el buscador de huéspedes estaba pendiente de la fase 4.

## Verificación al cierre de fase 4

Fase 4 implementada: suite completa de 219 pruebas PHP y 1541 aserciones; diez pruebas JavaScript correctas. Pint, Vite y compilación Blade correctos. Migración aplicada en SQLite local después de confirmar destino: nueve migraciones ejecutadas; admin y datos previos conservados. No se insertaron huéspedes reales ni tarifas ficticias.

Pendientes: revisión visual y recorrido real de alta en otra pestaña; pruebas de concurrencia sobre el motor final; integraciones del buscador en reservaciones, facturas y pagos durante sus fases. Las pruebas JavaScript verifican comportamiento mediante un sustituto del DOM. No se afirma validación de relaciones financieras que todavía no existen.

## Decisiones de fase 5 — 2026-10-01

Las respuestas posteriores del usuario modifican la lista inicial de estados y resuelven las reglas consultadas:

- Se incorpora `por_confirmar`. La última respuesta prevalece sobre la primera: **por_confirmar y pendiente no bloquean**; confirmada y hospedada sí bloquean el intervalo de estancia. Los avisos se muestran en listado/ficha, sin comunicaciones externas ni vencimientos automáticos.
- Flujo central: por_confirmar → pendiente o confirmada; pendiente → confirmada; confirmada → hospedada → finalizada. Cancelar y no presentado se permiten antes de hospedarse. El estado no se cambia mediante edición general.
- Reagendar antes de registrar entrada permite cambiar fechas, cabaña y huésped, conservando el mismo identificador de reservación. Se comprueba disponibilidad nuevamente, excluyendo solo el registro autorizado que se edita.
- Entrada habitual 14:00, salida 11:00, America/Tegucigalpa. Se almacenan fechas del negocio y los instantes previstos en UTC, además de los instantes reales registrados. Se permite la entrada el día de otra salida, siempre que los intervalos no se crucen.
- Los cinco estados manuales de la cabaña permanecen bajo control del personal. Solo disponible y activa permite nuevas operaciones de reservación; ocupada, reservada, mantenimiento y bloqueada impiden nuevas reservas. Las reservaciones no cambian automáticamente ese estado manual.
- Administración puede autorizar una hora de entrada temprana o salida tardía, con permisos explícitos y comprobación de cruces. Registrar una salida real fuera del horario aprobado exige el permiso administrativo de salida tardía. Otra entrada física se rechaza mientras exista una estancia hospedada sin cerrar, aunque haya vencido su salida prevista.
- **No presentado se permite al cumplirse la salida prevista**, según respuesta expresa del usuario. Antes, el personal puede cancelar por un aviso externo del cliente y liberar la cabaña. No se registran pagos, penalizaciones ni reembolsos en este módulo.
- Tarifas **HNL y USD independientes**, seleccionadas mediante lista en la reservación. Los campos existentes del catálogo se identifican como HNL; se añaden dos campos USD opcionales, sin rellenarlos con cifras inventadas. No había cabañas existentes al verificar el destino. Elegir USD exige configurar ambas tarifas en dólares. No existe conversión ni tipo de cambio implícito.
- Fórmula aprobada: por cada noche, precio base más precio por cada huésped que exceda la capacidad base. Se usan decimales exactos de `brick/math`, ya presente en las dependencias bloqueadas de Laravel, sin añadir paquetes ni usar flotantes para importes.
- Al reagendar, el personal elige conservar tarifa/capacidad base históricas o usar las actuales de la cabaña/moneda elegidas. Cambiar moneda exige tarifa actual de esa moneda; mantener una tarifa no cambia su denominación. Se conserva copia de precios, capacidad base, adicionales, noches, moneda y subtotal; no se recalcula al modificar después el catálogo.
- El buscador compartido conserva la selección explícita y todos los otros campos tras errores y permite cambiar cliente. Guardar exige reservaciones.crear/editar y huespedes.buscar; confirmar revalida el huésped actual. Inactivos/lista negra siguen seleccionables con advertencia; eliminados no se admiten. Las relaciones históricas pueden recuperar registros eliminados lógicamente.
- Disponibilidad y escritura se comprueban bajo el mismo bloqueo de cabaña; cambios de cabaña adquieren los bloqueos por orden de ID. SQLite usa una escritura sin alterar valores antes de leer disponibilidad, porque FOR UPDATE no se implementa en ese motor. Dos procesos independientes verifican la exclusión de confirmaciones simultáneas.
- El catálogo impide eliminar una cabaña con reservaciones vigentes o reducir su capacidad máxima por debajo de su ocupación asignada. La eliminación de reservaciones es lógica; exige cancelar primero las confirmadas y conserva estancias registradas.
- La auditoría de reservaciones conserva estados, relaciones, horarios e importes; excluye notas y valores personales. La ficha de huésped muestra sus reservaciones y estancias únicamente con reservaciones.ver.
- Tres permisos nuevos: reservaciones.marcar_no_presentado, reservaciones.autorizar_entrada_temprana y reservaciones.autorizar_salida_tardia. La asignación inicial se limita al rol administrador; los demás roles no reciben privilegios por consultar una pantalla.

Servicios, impuestos, descuentos, facturas, pagos y sincronización iCalendar siguen en sus fases posteriores. No se crean tarifas por temporada, cargos por horarios excepcionales, políticas de cancelación ni automatizaciones sin reglas aprobadas.

### Precisión monetaria en SQLite

La prueba adicional con un subtotal grande dentro del rango detectó pérdida de un centavo al persistir `DECIMAL(16,2)` en SQLite: ese motor aplica afinidad NUMERIC y puede almacenarlo como REAL. Se añadió una migración conservadora para guardar el subtotal como cadena decimal en SQLite, manteniendo su cast decimal y el cálculo exacto. Los motores con DECIMAL nativo conservan la columna numérica. No se modifican las migraciones ya ejecutadas; la corrección local se aplica cuando la tabla aún tiene cero reservaciones.

La reversión de esta migración no convierte subtotales existentes a flotante: conserva el formato exacto. Las consultas futuras de reportes deben respetar esta representación y usar aritmética decimal exacta. No se ejecutó ningún rollback ni borrado de datos.

## Verificación al cierre de fase 5

La suite completa final pasó con **326 pruebas PHP y 2108 aserciones**; diez pruebas JavaScript, Pint, compilación de Vite y caché de Blade correctos. Se probaron confirmaciones desde dos procesos en SQLite y la persistencia exacta de centavos con importes grandes.

Doce migraciones aplicadas tras comprobar el destino local. Admin activo con 72 permisos; se conservan el usuario y el registro de auditoría anteriores. Cero cabañas, huéspedes y reservaciones precargados, sin cambiar contraseñas ni borrar datos.

Revisión visual pendiente por falta de navegador automatizable. Concurrencia en otros motores pendiente si cambia el destino de despliegue. Facturas, pagos y calendario/iCalendar se implementarán y verificarán en sus fases; no se atribuyen a este módulo operaciones financieras aún inexistentes.

## Cambio a MySQL — 2026-10-01

- El usuario solicitó cambiar esta aplicación a MySQL, eligió la base `sistema_cabanas` y pidió conservar los datos actuales de SQLite.
- `.env` usa `DB_CONNECTION=mysql` y `DB_DATABASE=sistema_cabanas`. Se conservaron los demás valores del entorno y las credenciales que el usuario actualizó directamente. No se registraron secretos en estos documentos ni se modificó la clave de la aplicación.
- El servidor autenticado informó **MySQL 8.0.46**. Se comprobó que `sistema_cabanas` no existía antes de crearla con utf8mb4 y utf8mb4_unicode_ci. No se modificaron otras bases.
- Las doce migraciones existentes se aplicaron sin cambiar sus archivos. La corrección de precisión exclusiva de SQLite no ejecuta cambios sobre MySQL; `reservaciones.subtotal` conserva **DECIMAL(16,2)**. Todas las tablas destino usan InnoDB.
- Se copió el contenido de las diecisiete tablas de datos hacia el destino vacío en una transacción, respetando claves foráneas y conservando identificadores, hash de la contraseña, permisos, fechas, auditoría, sesión y caché. Se compararon todas las filas y columnas antes de confirmar; solo se normalizó la representación de números/JSON para la comparación. La tabla `migrations` pertenece a las migraciones ejecutadas en el destino y no se sobreescribió.
- Resultado: un usuario administrativo activo, un rol, 72 permisos, sus asignaciones y un registro de auditoría; una sesión y cuatro entradas de caché. No hay cabañas, huéspedes ni reservaciones existentes. La base `database/database.sqlite` se conserva y se consultó en modo de solo lectura.
- **Estado de la primera transferencia, posteriormente sustituido:** las pruebas automatizadas mantenían SQLite en memoria y sus protecciones de aislamiento. La ejecución de migraciones y las consultas de comprobación sí se realizaron contra MySQL. No se afirma cobertura completa ni verificación de concurrencia en MySQL; esas comprobaciones específicas siguen pendientes en una base de pruebas separada.

## Pruebas gráficas solicitadas — 2026-10-01

Se utiliza Edge independiente en modo headless, con perfil efímero y esquema MySQL propio, para no escribir datos ficticios en el sistema del usuario ni controlar su ventana. Control mediante protocolo DevTools y Node nativo, sin añadir paquetes. Recursos del build de Vite; `public/hot` y servidor en 8000 conservados.

Diez comprobaciones agrupadas correctas y ocho capturas. Verificados alta de cabaña/huésped, buscador compartido por los tres criterios con teclado, advertencias de inactivo/lista negra, conservación de campos, reservación/reagendado/confirmación/cancelación y acceso/cierre. Persistencia sin duplicar huésped; subtotal exacto 271.50. Sin errores JavaScript/HTTP. Se revisaron capturas de escritorio y móvil; quedan recorridos adicionales, detallados en `PRUEBAS_INTERFAZ.md`.

No se modificó comportamiento de negocio. CSRF no se considera probado en el recorrido de navegador por usar entorno testing; permanecen las pruebas HTTP específicas. Esquema y perfil temporales retirados, cero esquemas restantes. Pint y sintaxis Node correctos; no se repitió la suite completa ni el build. Esta comprobación no inicia la fase 6 ni amplía requisitos.
