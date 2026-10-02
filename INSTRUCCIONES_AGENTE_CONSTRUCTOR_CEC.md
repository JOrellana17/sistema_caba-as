# INSTRUCCIONES PARA RECONSTRUIR CEC DESDE LARAVEL LIMPIO

## Contexto

Debes construir desde cero el sistema administrativo de **Cabanas El Capitan** utilizando una instalacion limpia de Laravel.

Tu fuente principal de requisitos es `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`.

El documento contiene primero una auditoria del sistema legado y despues decisiones definitivas de reconstruccion. **Las secciones 72 en adelante tienen prioridad sobre cualquier contradiccion anterior.**

No copies el codigo del sistema legado. Utilizalo solamente como referencia funcional.

## Motor aprobado para la implementacion actual

Desde el 2026-10-01 se utiliza **MySQL**, tanto en la aplicacion como en las pruebas. La base operativa es `sistema_cabanas`; las pruebas usan esquemas nuevos aislados mediante `Tests\Support\BaseDatosMySql`. No configurar SQLite, repetir la transferencia ya realizada ni reiniciar bases existentes. Las migraciones historicas y el archivo SQLite anterior se conservan sin usarlos como destinos activos. El plan de esta adecuacion esta en `docs/PLAN_CAMBIO_MYSQL.md`.

## Reglas obligatorias

- Todo el codigo de negocio debe nombrarse en español sin tildes ni `ñ`.
- La interfaz visible debe estar correctamente escrita en español, incluyendo tildes y `ñ`.
- Mantener convenciones internas de Laravel cuando sean necesarias (`created_at`, `updated_at`, etc.).
- El alojamiento se representa mediante una sola entidad `Cabana` y la tabla `cabanas`.
- No existen pisos, edificios ni habitaciones internas.
- No existe gestion de limpieza dentro del sistema.
- No crear campos de negocio `floor`, `building`, `cleaning_status`, `smoking` o `balcony`.
- Debe existir una sola entidad `Reservacion`; no crear `Booking` y `Reservation` paralelos.
- Toda disponibilidad debe pasar por una unica implementacion, por ejemplo `ServicioDisponibilidad`.
- Toda regla de precios debe tener una fuente coherente y los precios aplicados deben quedar como snapshot en la reservacion/factura.
- Los impuestos deben obtenerse de una unica configuracion.
- Los permisos deben ser explicitos por accion.
- No deducir permisos sensibles a partir del nombre de las rutas.
- No hardcodear contraseñas, impuestos ni secretos.
- No generar consecutivos con `max(id)+1`.
- Pagos, facturacion y reembolsos deben utilizar transacciones.
- Los reembolsos deben conservar historial auditable.
- La importacion iCalendar por URL debe protegerse contra SSRF.
- Crear pruebas para autenticacion, autorizacion, disponibilidad, reservaciones, facturacion, pagos, reembolsos e iCalendar.

## Modulos objetivo

1. Panel.
2. Cabanas.
3. Huespedes.
4. Reservaciones.
5. Calendario.
6. Servicios adicionales.
7. Facturas.
8. Pagos.
9. Reportes.
10. Usuarios.
11. Roles.
12. Permisos.
13. Auditoria.
14. Configuracion.

## Modelo de cabana

La cabana es una estancia independiente.

Base sugerida:

```text
cabanas
- id
- nombre
- slug
- descripcion
- capacidad_base
- capacidad_maxima
- precio_base
- precio_persona_adicional
- estado
- activa
- imagen_principal
- notas
- creado_por
- created_at
- updated_at
- deleted_at
```

Estados:

```text
disponible
ocupada
reservada
mantenimiento
bloqueada
```

No agregar informacion de piso, edificio o limpieza.

## Modelo central de reservacion

Tabla:

```text
reservaciones
```

Debe relacionar:

```text
huesped
cabana
servicios
factura
calendario
auditoria
```

Debe cubrir todo el ciclo de vida:

```text
pendiente
confirmada
check_in
check_out
cancelada
no_presentado
```

Selecciona una convencion definitiva para los estados y usala en todo el proyecto.

## Nombres de negocio

Usa como referencia:

```text
Cabana
Huesped
Reservacion
Servicio
Factura
DetalleFactura
Pago
Reembolso
Usuario
Rol
Permiso
Configuracion
RegistroAuditoria
ReglaPrecioCabana
EventoCalendario
```

Tablas:

```text
cabanas
huespedes
reservaciones
servicios
reservacion_servicio
facturas
detalles_factura
pagos
reembolsos
usuarios
roles
permisos
rol_usuario
permiso_rol
permiso_usuario
configuraciones
registros_auditoria
reglas_precio_cabana
eventos_calendario
```

## Orden de construccion

Construye por etapas y valida cada una antes de continuar:

1. Fundacion Laravel y autenticacion.
2. Usuarios, roles, permisos y auditoria.
3. Cabanas.
4. Huespedes.
5. Reservaciones y disponibilidad.
6. Servicios adicionales.
7. Facturacion.
8. Pagos y reembolsos.
9. Calendario e iCalendar.
10. Reportes.
11. Configuracion.
12. Dashboard.
13. Revision integral, seguridad y pruebas.

Para cada etapa:

1. define migraciones;
2. define modelos y relaciones;
3. define reglas de negocio;
4. define permisos;
5. crea validaciones;
6. implementa casos de uso;
7. implementa interfaz;
8. agrega auditoria cuando corresponda;
9. crea pruebas;
10. ejecuta las pruebas antes de continuar.

## Restricciones

No intentes preservar la estructura de base de datos antigua. El proyecto parte de una base nueva.

No agregues funcionalidades simplemente porque aparecen en el codigo legado si contradicen la especificacion nueva.

No inventes decisiones sobre dinero, impuestos, seguridad o integridad de datos. Si `SYSTEM_SPEC_CEC_RECONSTRUCCION.md` no permite resolver una decision importante, detente en ese punto, documenta exactamente la duda y solicita aclaracion.

## Primera tarea

Antes de comenzar a escribir modulos funcionales:

1. lee completo `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`;
2. genera un plan de implementacion por fases;
3. propone el esquema inicial de base de datos en español;
4. identifica cualquier contradiccion restante;
5. crea el proyecto Laravel limpio;
6. configura autenticacion y estructura base;
7. comienza la implementacion siguiendo el orden establecido.

No copies archivos del proyecto anterior.

---

## Requisito adicional aprobado: buscar y reutilizar clientes

Implementa la seccion 86 de `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`: busqueda de clientes por nombre, telefono o numero de identidad al crear una reservacion, factura o movimiento ya definido que requiera seleccionar un cliente.

Este es el unico requisito nuevo aprobado en esta edicion. Las otras mejoras propuestas en la conversacion no se agregan como obligaciones de construccion.

### Reglas obligatorias de la busqueda

- Reutiliza `Huesped` y `huespedes`; no crees `Cliente`, `clientes` ni registros duplicados por operacion.
- Crea una unica consulta en el servidor y un componente Blade compartido para los formularios.
- Permite introducir nombre, telefono o identidad en un mismo buscador.
- Busca nombres parcialmente, sin diferenciar mayusculas ni tildes.
- Compara telefono e identidad sin separadores de formato, preservando los datos originales y los ceros iniciales. No asumas prefijos internacionales ni un unico formato nacional de documento.
- Consulta desde tres caracteres utiles y limita cada respuesta a 20 resultados.
- Muestra nombre, telefono e identidad segun permisos; no expongas notas privadas ni informacion financiera ajena a la seleccion.
- Exige seleccion explicita y conserva el `huesped_id`; no elijas automaticamente entre coincidencias.
- Conserva los otros datos del formulario al cambiar de cliente o regresar de un alta.
- Si no hay coincidencias, permite crear un huesped por el flujo existente solo con `huespedes.crear`; nunca lo crees automaticamente.
- Agrega `huespedes.buscar` a permisos y datos iniciales, con asignacion a roles segun la matriz. Protege la consulta en el servidor.
- Revalida al guardar el huesped, su condicion y la coherencia con la reservacion o factura; respeta las reglas existentes sobre inactivos, lista negra y eliminacion logica.
- No conviertas la seleccion de cliente en un pago: conserva las relaciones y validaciones financieras obligatorias.
- Diferencia carga, ausencia de resultados y error; evita respuestas atrasadas y permite seleccion con teclado.

### Orden y verificacion

Construye el buscador en la etapa 4, Huespedes. Integralo durante las etapas 5, Reservaciones; 7, Facturacion; y 8, Pagos, cuando corresponda al flujo existente.

Antes de considerar terminada cada integracion, verifica los criterios de aceptacion de la seccion 86 y ejecuta pruebas de busqueda por los tres criterios, coincidencias multiples, autorizacion, seleccion sin duplicados y coherencia entre cliente y documentos.

Manten las decisiones previas sobre cabanas independientes, ausencia de gestion de limpieza y nomenclatura de negocio en espanol sin tildes ni `ñ`.
