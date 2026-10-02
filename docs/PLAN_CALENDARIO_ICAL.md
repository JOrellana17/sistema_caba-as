# Plan de calendario e iCalendar — Cabañas El Capitán

Fecha de investigación: 2026-10-01. Estado: **plan preparado; integración todavía no implementada**.

Este documento prepara la fase 9 del proyecto. Conserva Laravel 13, MySQL, Blade + Bootstrap 5, una entidad Reservacion, una entidad Huesped y ServicioDisponibilidad. No cambia el orden de construcción de los módulos ni autoriza crear precios, huéspedes ficticios o registros financieros desde un calendario.

## 1. Resultado de la investigación

Airbnb publica un enlace de calendario y permite importar enlaces externos. Su ayuda indica actualización automática cada tres horas, actualización manual y tratamiento de noches reservadas o bloqueadas. Algunas reglas de disponibilidad de otras plataformas pueden no trasladarse igual. Es un intercambio periódico, no una confirmación instantánea de disponibilidad. Fuente: [Ayuda oficial de Airbnb](https://www.airbnb.com/help/article/99).

Booking.com Partners publica un [tutorial oficial de sincronización entre plataformas desde la extranet](https://www.youtube.com/watch?v=Smj-WfWpi4o). Se pudo consultar su descripción indexada, no reproducir el tutorial. Su [artículo oficial de calendarios](https://partner.booking.com/en-us/help/rates-availability/extranet-calendar/syncing-your-bookingcom-calendar-third-party-calendars) devolvió HTTP 403. **No se consideran verificados su intervalo exacto de actualización, límites de alojamientos ni las opciones concretas de la cuenta CEC.** Se comprobarán en la extranet antes del piloto; las cifras difundidas por terceros no se convierten en requisitos del sistema.

La API de conectividad de Booking es otra integración: administra disponibilidad, reservaciones y precios, y requiere su proceso de incorporación como proveedor. No se necesita implementar esa API para empezar con iCalendar. Fuente: [Documentación oficial de Connectivity APIs](https://developers.booking.com/connectivity/docs).

El estándar iCalendar intercambia eventos: UID identifica el evento, DTSTART es el inicio y DTEND es el final exclusivo. Una fecha final no representa otra noche ocupada. Fuente: [RFC 5545, sección 3.6.1](https://www.rfc-editor.org/rfc/rfc5545#section-3.6.1).

**Inferencia para CEC:** la presencia de eventos de disponibilidad no garantiza nombre, teléfono, identidad, ocupación, moneda, precio acordado, pagos ni una confirmación comercial completa. El parser puede recibir información adicional, pero no la tratará como un contrato estable de Airbnb/Booking sin muestras y documentación verificadas. No se convertirán títulos como “Reserved” o “Not available” en una clasificación definitiva por sí solos.

## 2. Decisiones confirmadas por el usuario

| Decisión | Respuesta incorporada |
| --- | --- |
| Evento externo sin datos completos | Bloquear automáticamente las fechas; el personal completa después la reservación |
| Evento que desaparece | Liberar tras comprobación automática; un fallo de conexión nunca libera fechas |
| Dirección de sincronización | Ambos sentidos: importar Airbnb/Booking y exportar la disponibilidad de CEC |

Siguen vigentes: sistema interno; por_confirmar y pendiente no bloquean; confirmada y hospedada sí; horarios habituales 14:00/11:00, America/Tegucigalpa; estados manuales de cabaña bajo control del personal; nombre/teléfono obligatorios e identidad opcional; HNL/USD independientes.

## 3. Funcionamiento propuesto para el personal

1. En Calendario, elegir una cabaña y configurar sus enlaces externos de Airbnb y Booking, con nombres claros y mapeo explícito.
2. Revisar una previsualización inicial: fechas, nuevos eventos, cambios, duplicados y cruces con reservaciones existentes.
3. Activar la fuente. El sistema consulta periódicamente y muestra última lectura correcta, próxima consulta y cualquier error.
4. Un evento recibido reserva disponibilidad mediante EventoCalendario, aunque todavía no tenga huésped ni importes. Se muestra como **bloqueo externo pendiente de completar**; ese texto no añade un estado a Reservacion.
5. Abrir el evento y completar o vincular una reservación: buscar al Huesped existente o darlo de alta con el componente compartido. Introducir los datos reales de la operación.
6. CEC ofrece un enlace .ics por cabaña y destino para pegarlo en cada plataforma. Las plataformas consultan ese enlace según su propio funcionamiento.
7. Si se comprueba una baja externa, retirar su bloqueo conservando historial. Si queda otra causa de indisponibilidad, la cabaña continúa bloqueada.

El personal ve conflictos y errores en el sistema. No se añaden correos, WhatsApp, reservas públicas ni automatismos de cobro.

## 4. Diseño sobre la aplicación existente

### Modelos y migraciones nuevas propuestos

| Entidad / tabla | Responsabilidad y datos principales |
| --- | --- |
| FuenteCalendario / fuentes_calendario | cabana_id, proveedor, nombre, URL cifrada, activa, versión de configuración, estado de sincronización, última lectura correcta, próxima consulta, ETag/Last-Modified y error resumido sin secretos |
| EventoCalendario / eventos_calendario | fuente_id, cabana_id, UID original, RECURRENCE-ID cuando exista, huellas de identidad/contenido, fechas originales y periodo normalizado, estado de integración, bloquea, reservacion_id nullable, última lectura donde se vio y comprobación de ausencia |
| EnlaceCalendario / enlaces_calendario | cabaña o alcance general, destino, hash del token, activación/revocación y fecha de creación; controla exportaciones sin mezclar credenciales entrantes/salientes |
| SincronizacionCalendario / sincronizaciones_calendario | ejecución por fuente, inicio/fin, resultado, lectura completa o parcial y cantidades de altas/cambios/bajas/conflictos; historial operativo sin contenido sensible |

Son tablas de integración, no una segunda estructura de reservaciones. Las reservaciones locales existentes se leen directamente: no crear una copia mutable paralela por cada reservación para el calendario visual.

Usar claves foráneas que conserven historial e índices para fuente/identidad y cabaña/periodo/bloqueo. La identidad externa será única por fuente + UID + RECURRENCE-ID, preservando exactamente sus caracteres; no aplicar normalización de nombres ni una intercalación que iguale identificadores distintos. Si se indexan huellas, verificar también el identificador original antes de actualizar. Dos fuentes pueden usar el mismo UID sin corresponder a la misma operación.

### Servicios propuestos

- ServicioLecturaCalendario: transporte seguro, lectura acotada y validación del documento.
- ServicioSincronizacionCalendario: previsualización y reconciliación idempotente por fuente.
- ServicioExportacionCalendario: generación de feeds y UID estables, con filtrado por destino.
- Extender ServicioDisponibilidad: considerar reservaciones bloqueantes **y** eventos externos bloqueantes. No crear otra lógica de disponibilidad exclusiva para el calendario.
- Reutilizar ServicioBloqueoCabanas, ServicioReservaciones, ServicioSeleccionHuesped y ServicioAuditoria en los casos de uso que correspondan.

Proponer sabre/vobject para leer y generar ICS; la biblioteca está documentada para PHP e iCalendar. Actualmente no está en composer.lock ni instalada. Antes de incorporarla se verificará compatibilidad con PHP/dependencias y se actualizará el bloqueo de forma reproducible. Fuente: [Documentación de sabre/vobject](https://sabre.io/vobject/).

## 5. Importación, cambios y liberación automática

Algoritmo propuesto:

1. Tomar un bloqueo de ejecución por fuente y una versión de su configuración. Descartar resultados de trabajos anteriores si la fuente se desactivó, cambió de URL o cambió de cabaña.
2. Descargar fuera de la transacción MySQL, con transporte validado y límites de bytes, tiempo y eventos.
3. Analizar y normalizar, sin guardar huéspedes ni importes desde el texto del evento. Registrar errores distinguiendo un calendario vacío válido de HTML de acceso, un documento truncado o un fallo de red.
4. Adquirir el bloqueo MySQL de la cabaña antes de aplicar cambios. Escritura, disponibilidad, relaciones e historial en una transacción corta; el caso concurrente debe usar el mismo bloqueo que las reservaciones.
5. Mismo identificador y contenido: sin nueva alta. Mismo identificador con otras fechas: actualizar el evento, comprobar cruces y auditar. UID nuevo: nuevo evento. Reaparece un UID retirado: reactivar el mismo registro si corresponde, sin duplicarlo.
6. Los cruces se conservan como conflictos visibles. Un evento externo real sigue bloqueando su intervalo; no se elimina una reservación local ni se elige silenciosamente un ganador. La disponibilidad resulta de la unión de bloqueos.
7. Reconciliar ausencias solo tras una lectura completa y correcta. Una lectura parcial puede añadir bloqueos válidos, pero nunca retirar otros por no haberlos encontrado.
8. **Propuesta técnica de comprobación de eliminación:** dos lecturas completas correctas consecutivas sin el evento, separadas por el intervalo configurado, dentro de la ventana de cobertura conocida. Mantener el bloqueo durante la comprobación. CANCELLED explícito debe validarse por fuente/identidad antes de procesarse.
9. No contar timeout, 401/403, 429, 5xx, formato inválido, descarga limitada o HTTP 304 como una nueva confirmación de ausencia. 304 conserva la última instantánea correcta.
10. Tras baja verificada, marcar retirado y dejar de bloquear; conservar fechas e historial. Nunca borrar físicamente los registros de integración.

Dos lecturas es una propuesta de implementación de la comprobación autorizada, no una política publicada por los proveedores. Se ensayará con calendarios vacíos, reducción de horizonte y bajas masivas. Si no se puede demostrar cobertura de las fechas, marcar incidencia y no suponer cancelación.

**Reservación local vinculada:** retirar el evento externo no equivale a anular facturas, reembolsar pagos o finalizar una estancia. La política de propagación a una reservación local confirmada queda pendiente de precisar; una hospedada no puede cancelarse saltando el flujo actual. Hasta resolverla, el plan permite bajas automáticas del bloqueo externo, manteniendo otras causas de indisponibilidad y avisando al personal.

**Completar o vincular sin colisión consigo mismo:** la operación autorizada valida que evento y reservación corresponden a la misma cabaña y estancia, y adquiere su bloqueo transaccional. Al confirmar, ServicioDisponibilidad puede excluir exclusivamente el evento vinculado a esa misma operación, manteniendo todos los demás bloqueos. Ningún ID enviado por el navegador permite ignorar arbitrariamente eventos. Conservar el enlace auditable y prevenir dos reservaciones locales para un mismo evento; no fusionar por coincidencia de nombre, fechas o teléfono.

Los cambios de fechas de un evento vinculado no reagendan ni recalculan automáticamente su reservación local mientras no se defina esa regla. Marcar discrepancia visible y conservar la unión de intervalos bloqueantes hasta resolverla; así tampoco se modifican precios históricos o estados de estancia desde un feed.

## 6. Fechas, noches y horarios

Ejemplo ficticio: DTSTART;VALUE=DATE:20261005 y DTEND;VALUE=DATE:20261007 describen un intervalo de fechas que termina el día 7 sin incluirlo; no se añadirá otra noche por restar o sumar días de forma indiscriminada. El estándar permite fechas y fechas con hora. Fuente: [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545#section-3.6.1).

El adaptador conservará el tipo original. Para eventos identificados como estancias por el contrato observado, traducir noches a los horarios CEC 14:00/11:00. Para cierres de días completos, respetar su intervalo completo. Si el proveedor no permite distinguirlos, aplicar una interpretación conservadora documentada y mostrar la incertidumbre; la regla concreta se valida con muestras antes de activar esa fuente.

DATE no debe desplazarse por convertir medianoche a UTC. DATE-TIME con TZID/UTC se normaliza correctamente. Las fechas con hora sin zona necesitan una configuración explícita de la fuente. Manejar o rechazar de forma visible recurrencias, excepciones, DURATION y zonas desconocidas; nunca ignorar silenciosamente un evento que pudiera bloquear.

Las excepciones de entrada temprana/salida tardía siguen requiriendo autorización y la consulta central. iCalendar de noches no garantiza que un destino aplique restricciones horarias: mostrar esa limitación y verificar casos de salida tardía que alcance otra noche.

## 7. Exportación, privacidad y prevención de ciclos

- Feed .ics por cabaña; enlace distinto por destino para revocación y filtrado independientes. Mantener también exportación general y de rangos como salidas administrativas, con alcance explícito; no mapear un feed general a una sola cabaña externa.
- UID estable derivado de identificador persistente, independiente del token y del orden de consulta. Reagendar conserva UID; los consumidores podrán reconocer cambios.
- Exportar por defecto las reservaciones confirmada/hospedada dentro del rango. No exportar por_confirmar, pendiente, cancelada o no_presentado como noches ocupadas. Los registros históricos conservan su trazabilidad, sin confundirse con disponibilidad actual.
- Exponer únicamente identificador opaco y fechas de indisponibilidad. SUMMARY genérico, por ejemplo No disponible. Sin nombre, teléfono, identidad, notas, importes, servicios ni enlaces a fichas internas.
- Endpoint externo de solo lectura, protegido por token aleatorio y revocable. Administración de enlaces autenticada con CSRF y permiso propio; esto no abre un portal público de reservaciones. Hash de token almacenado; emisión visible una vez y regeneración explícita.
- No importar el feed propio como una nueva ocupación. Reconocer UID locales y procedencia al volver desde una plataforma; conservar UID de los eventos reenviados.
- No reenviar al destino sus propios eventos. **La exclusión de origen por sí sola no garantiza eliminar los ciclos** si un proveedor cambia UID o convierte una reserva ajena en un bloqueo nuevo.

La topología se decide y valida en el piloto: documentar todas las conexiones Airbnb ↔ Booking y CEC ↔ cada plataforma. Primero probar exportación de reservaciones locales de CEC y recepción de cada canal. El reenvío automático de bloqueos de Airbnb hacia Booking a través de CEC, y viceversa, se activa solo si las muestras permiten identificar ecos y retirarlos al cancelar. Evitar conexiones cruzadas redundantes; no fusionar reservas diferentes solo porque tienen las mismas fechas.

Condición de salida a producción: cancelar un evento debe liberar exactamente sus bloqueos después de propagarse, sin dejar copias que se bloqueen entre sí. Si las plataformas no conservan identidad/procedencia suficiente, registrar la limitación y escoger una topología verificable antes de habilitar reenvío de eventos importados. La comunicación bidireccional CEC/plataforma continúa prevista; la cobertura completa entre ambos canales requiere esta comprobación.

Los estados manuales de cabaña carecen hoy de rango temporal. Falta definir qué intervalo se exporta para mantenimiento, bloqueada, ocupada o reservada manualmente; no convertirlos en un cierre perpetuo ni inventar fecha final. La exportación debe advertir su cobertura hasta resolver esta regla.

## 8. Seguridad y operación

Transporte propuesto: HTTPS, hosts autorizados comprobados, sin credenciales embebidas ni protocolos alternativos; validar IPv4/IPv6, rechazar localhost, redes privadas, link-local y metadata; comprobar resolución DNS y conectar a una dirección pública validada sin perder verificación TLS. Redirecciones deshabilitadas inicialmente; cualquier excepción futura debe revalidar cada destino. Limitar descarga en streaming y contenido descomprimido, tiempo y complejidad del parser; no basta con comprobar Content-Length. Rechazar adjuntos y no resolver enlaces contenidos en el archivo. Estas medidas se basan en [OWASP: prevención de SSRF](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html).

URLs de importación cifradas y ocultas; tokens/URLs completas y datos personales excluidos de auditoría, errores, logs de acceso del servidor y de proxy. No cargar feeds externos desde JavaScript ni exponer secretos al navegador. El frontend recibe JSON autorizado del servidor.

Permisos: conservar reservaciones.ver_calendario para lectura. Proponer permisos explícitos de administrar fuentes, sincronizar manualmente, importar, exportar, gestionar enlaces y resolver conflictos. Vincular/completar exige también los permisos existentes de reservaciones y huéspedes. El cron usa un actor de sistema auditado, sin conceder privilegios por una consulta. La matriz de asignación se definirá con los roles del personal.

Programador de Laravel y cola existente, con trabajo por fuente, exclusión de ejecuciones solapadas, reintentos acotados, espera creciente ante errores transitorios y límites para el botón Sincronizar ahora. Punto de partida técnico propuesto: consultar cada 15 minutos, configurable tras observar respuestas y límites; no es la frecuencia de Airbnb ni una garantía sobre Booking. Ajustar timeout del trabajo, timeout del worker y retry_after de forma coherente. Fuente: [Programador de Laravel](https://laravel.com/docs/13.x/scheduling).

Para importar localmente, el equipo debe tener salida a Internet y mantener programador/worker activos. Para que las plataformas lean CEC, el enlace debe tener dominio HTTPS accesible desde Internet: 127.0.0.1/Laragon no sirve como destino externo. No se publicará la aplicación ni se abrirán puertos como parte de este plan.

Mostrar última sincronización correcta por fuente, antigüedad y conflictos; una sincronización pendiente/error no se presenta como disponibilidad garantizada. Consultar CEC cada 15 minutos no obliga a las plataformas a importar CEC cada 15 minutos. Puede existir una ventana de doble reserva entre lecturas; iCalendar no aporta bloqueo distribuido instantáneo.

## 9. Calendario visual

Calendario mensual y lista móvil, filtros por cabaña/origen/estado, leyenda, enlaces a reservaciones autorizadas y ficha de eventos externos con acciones para completar o revisar conflicto. Inicio incorporará una tarjeta de Calendario con su permiso cuando la ruta exista.

Evaluar FullCalendar con integración Bootstrap 5 y módulos estándar vía Vite, sin CDN, React/Vue ni adquisición de plugins premium. El producto dispone de documentación de integración Bootstrap; fijar una versión compatible al implementar. Fuente: [FullCalendar Bootstrap 5](https://fullcalendar.io/docs/bootstrap5).

El calendario visual consume disponibilidad normalizada desde Laravel. El plugin de lectura ICS del navegador no sustituye al importador seguro del servidor. Los datos personales siguen condicionados a sus permisos. Editar desde calendario reutiliza los casos de uso de reservaciones; no se agrega arrastrar/reagendar sin validación ni cambios de estados manuales de la cabaña por sincronizar.

## 10. Secuencia de implementación y verificaciones

| Paso | Entrega | Dependencias / condición | Verificación antes de continuar |
| --- | --- | --- | --- |
| 0 | Inventario de anuncios, mapa cabaña ↔ feed y muestras sanitizadas | Reglas pendientes y acceso de personal a extranet | Fechas, UID, bajas, alcance y comportamiento de eco comprobados por proveedor |
| 1 | Migraciones, modelos, permisos e historial | Diseño revisado; MySQL de pruebas | Preservación de datos, claves, identidad externa única y autorización |
| 2 | Parser, archivo ICS y previsualización | Parser compatible y fixtures de ambas plataformas | Eventos inválidos, límites, fechas, recurrencias, identidad y privacidad |
| 3 | URL segura y consulta manual | SSRF, timeouts, límites y secretos resueltos | DNS/redirects/IPs peligrosas rechazadas con sustitutos; errores no liberan fechas |
| 4 | Bloqueo automático en disponibilidad | ServicioDisponibilidad y bloqueo transaccional existentes | Todas las consultas/confirmaciones/ediciones detectan eventos; concurrencia con reservación y sincronización |
| 5 | Reconciliación y bajas automáticas | Identidad estable y cobertura conocida | Dos lecturas correctas, vacío válido, 304, parcial, cambios, reapariciones y otra fuente que aún bloquea |
| 6 | Exportación privada por enlace | Política de estados, rango, identidad y privacidad | ICS válido, UID estable, tokens revocados, permisos, sin datos personales |
| 7 | Sincronización programada y control operativo | Worker/programador supervisados | Reintentos, solapamientos, fuente desactivada, rotación y antigüedad visibles |
| 8 | Calendario visual y completar/vincular | Importación/disponibilidad validadas | Blade, móvil/escritorio, buscador/alta rápida, mismo huésped, ninguna alteración financiera |
| 9 | Piloto bidireccional de una cabaña | Dominio HTTPS, topología y extranet verificadas | Alta, reprogramación y baja en ambas direcciones; medir demora real; probar ecos antes de reenviar importados |
| 10 | Habilitación gradual de otras cabañas | Piloto correcto y operación documentada | Mapeo individual, estado de fuentes y reversión por fuente sin borrar historial |

Las verificaciones usan MySQL separado mediante Tests\Support\BaseDatosMySql y Http::fake/Http::preventStrayRequests. Sin llamadas a anuncios reales en las pruebas automatizadas. Fixtures sintéticos o muestras sanitizadas sin URLs privadas ni datos personales. Pint, php artisan test, pruebas JavaScript aplicables, npm.cmd run build, caché Blade y revisión en navegador. Los resultados se registran cuando se ejecuten, no se presentan hoy como pruebas superadas.

El piloto con plataformas reales necesita coordinación operativa: las pruebas pueden cerrar fechas de venta. Primero ensayar feeds de prueba y previsualizaciones; no conectar ni alterar calendarios comerciales para verificar una pantalla.

## 11. Pendientes concretos antes de activar

| Pregunta / dato | Impacto |
| --- | --- |
| ¿Cada anuncio/feed corresponde a una sola cabaña real? ¿Booking permite import/export en esa cuenta? | Evitar asignación incorrecta de inventario; no inventar habitaciones o tipos dentro de CEC |
| ¿Qué rango exportan los estados manuales de la cabaña y cuál es el horizonte del feed? | No cerrar venta indefinidamente ni publicar disponibilidad incompleta |
| Cuando un evento ya esté vinculado, ¿la baja externa debe cancelar automáticamente una reservación local confirmada aún sin entrada, o avisar para su cancelación? | Cumplir estados y separar anulaciones financieras; las hospedadas requieren tratamiento distinto |
| ¿Cómo traducir cierres y eventos ambiguos sin hora en cada proveedor? | Precisión de disponibilidad y entradas/salidas excepcionales |
| ¿Cuál será el dominio HTTPS y dónde permanecerán activos programador y worker? | Hacer posible el sentido CEC → plataformas |
| ¿Qué conexiones cruzadas existen actualmente entre canales y cómo conservan UID? | Evitar duplicados, reimportación propia y bloqueos persistentes después de cancelar |

Bloqueo automático, baja tras comprobación y ambos sentidos ya están resueltos. Estas preguntas no los reabren: delimitan casos que iCalendar y el modelo actual no resuelven por sí solos.

## 12. Estado de este trabajo

Investigación de documentación pública y revisión de modelos, disponibilidad, bloqueo, permisos, colas y dependencias existentes completadas. Plan y decisiones de seguimiento registrados. No se instalaron bibliotecas, modificó código funcional, ejecutaron migraciones, accedió a cuentas privadas ni se conectaron feeds reales. La ayuda de Booking devolvió 403; no se afirma comprobación de su intervalo/eligibilidad ni de muestras reales.
