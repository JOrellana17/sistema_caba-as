# SYSTEM_SPEC.md
# Especificación de auditoría y reconstrucción — CEC / SIGEH-PHP

> **Propósito de este documento:** servir como fuente de contexto para un segundo agente de desarrollo que reconstruirá el sistema desde cero.
>
> **Regla principal:** este documento describe el comportamiento y la estructura **observados en el código entregado**, pero no considera que toda la implementación actual sea correcta. Las secciones de problemas y decisiones de reconstrucción separan lo que existe de lo que debe corregirse.
>
> **Fecha de auditoría:** 2026-09-29
>
> **Proyecto analizado:** `CEC.zip`
>
> **Estado del ZIP:** el árbol de trabajo corresponde a la rama Git `main`. El repositorio también contiene una rama `feature/refactor-pendientes`, pero sus cambios no forman parte del árbol de trabajo analizado y NO deben asumirse como funcionalidades actualmente desplegadas.

---

# 1. Resumen ejecutivo

El proyecto es un sistema web administrativo para **Cabañas El Capitán / SIGEH-PHP**, orientado a la administración de alojamiento.

La implementación actual es una aplicación Laravel server-rendered con Blade, Bootstrap y algunos recursos Tailwind/Vite. El sistema cubre:

- autenticación de personal;
- dashboard;
- usuarios;
- roles;
- permisos;
- habitaciones;
- tipos de habitación;
- pisos;
- huéspedes;
- reservas;
- bookings/estancias;
- servicios;
- facturación;
- pagos;
- reembolsos;
- reportes operativos, financieros y estadísticos;
- auditoría;
- calendario;
- importación/exportación iCalendar;
- configuración general, de reservas, pagos, notificaciones y correo.

La aplicación tiene una base funcional considerable, pero presenta una arquitectura inconsistente porque varias responsabilidades fueron agregándose de manera incremental.

## Problemas estructurales principales detectados

1. Existen dos conceptos paralelos para gestionar alojamiento:
   - `bookings`
   - `reservations`

2. Existen dos mecanismos relacionados con roles/permisos:
   - Spatie Laravel Permission.
   - Un `role_id` directo en `users`.
   - Además existen tablas pivote propias que sustituyen las tablas estándar de Spatie.

3. Existen reglas de autorización automáticas basadas en el nombre de la ruta. Esto simplifica la implementación, pero produce permisos incorrectos o demasiado amplios en determinadas rutas.

4. Parte de la lógica de negocio está en:
   - modelos;
   - controladores;
   - servicios;
   - Form Requests;
   - middleware;
   - observers.

   No existe una frontera única y consistente.

5. La facturación de `Booking` y la facturación de `Reservation` no utilizan exactamente el mismo flujo.

6. Los reportes mezclan datos de `Booking` y `Reservation`, lo cual puede producir métricas inconsistentes.

7. La interfaz utiliza Bootstrap 5 mediante CDN mientras `resources/css/app.css` importa Tailwind 4. El sistema, por tanto, tiene dos enfoques de estilos simultáneos.

8. La cobertura de pruebas del árbol `main` es mínima: únicamente se observan tests de ejemplo en `tests/Unit` y `tests/Feature`. La rama de refactor contiene tests adicionales, pero esos cambios no están en el árbol de trabajo actual.

9. El README contiene credenciales predeterminadas que no coinciden con el usuario creado actualmente por `DatabaseSeeder`.

10. El código contiene operaciones sensibles que deberían quedar detrás de permisos explícitos, pero algunas rutas son autorizadas por un permiso de visualización debido al algoritmo genérico del middleware.

---

# 2. Tecnología actual

## Backend

- PHP `^8.3`
- Laravel `^13.0`
- Laravel Framework observado en `composer.lock`: `v13.7.0`
- Blade
- Eloquent ORM
- Laravel Authentication
- Laravel Form Requests
- Laravel Middleware
- Laravel Rate Limiter

## Paquetes relevantes

- `spatie/laravel-permission` `7.4.1`
- `barryvdh/laravel-dompdf` `3.1.2`
- `phpoffice/phpspreadsheet`
- `sabre/vobject` `4.5.8`
- Laravel Tinker

## Frontend

- Blade
- Bootstrap 5.3 cargado mediante CDN
- Bootstrap Icons
- Tailwind CSS 4 presente en el pipeline Vite
- Vite
- FullCalendar 6.1.x
- JavaScript vanilla

## Exportación

- PDF mediante DomPDF
- Excel mediante PhpSpreadsheet
- iCalendar mediante Sabre VObject

## Arquitectura de frontend

Actualmente es principalmente:

```text
Blade
  ↓
Laravel Controllers
  ↓
Eloquent Models / Services
  ↓
Database
```

No existe una SPA principal.

---

# 3. Estado del repositorio

El ZIP contiene `.git`.

Rama activa del árbol entregado:

```text
main
```

Commit observado:

```text
17ccc72 version1.1.2
```

Existe también:

```text
feature/refactor-pendientes
```

Commit observado:

```text
d6d1111 refactor: consolidate enterprise roadmap progress and pending baseline
```

La rama `feature/refactor-pendientes` contiene una refactorización mucho mayor, con aproximadamente 130 archivos modificados en ese commit, incluyendo:

- DTOs;
- Events;
- Jobs;
- Repositories;
- Services;
- State classes;
- Value Objects;
- API V1;
- Notifications;
- pruebas adicionales;
- índices;
- documentación.

**Importante:** esa rama no debe tratarse como el sistema actual. Puede utilizarse como evidencia histórica de la dirección que se intentó tomar, pero el nuevo sistema debe diseñarse de forma independiente.

---

# 4. Estructura observada

Aproximadamente:

- 20 controllers;
- 20 models;
- 10 Form Requests;
- 4 services principales;
- 68 vistas Blade;
- 4 migrations;
- 151 rutas HTTP en el backend según `php artisan route:list`.

Directorios relevantes:

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   └── Backend/
│   ├── Middleware/
│   └── Requests/
├── Models/
├── Observers/
├── Providers/
└── Services/

database/
├── factories/
├── migrations/
└── seeders/

resources/
├── css/
├── js/
└── views/
    ├── auth/
    ├── backend/
    └── layouts/

routes/
├── console.php
└── web.php

docs/
├── API_ROUTES.md
├── DOCUMENTATION.md
└── INSTALLATION.md
```

---

# 5. Módulos funcionales

## 5.1 Autenticación

Ruta:

```text
GET  /login
POST /login
POST /logout
```

Características:

- login mediante `username`;
- contraseña;
- opción `remember`;
- regeneración de sesión después del login;
- logout;
- bloqueo temporal mediante Laravel RateLimiter;
- máximo configurado: 5 intentos;
- ventana de throttle: 1 minuto;
- registro de intentos exitosos y fallidos en auditoría;
- usuarios inactivos no pueden acceder.

Flujo:

```text
Login
  ↓
Rate limit
  ↓
Validación
  ↓
Auth::attempt()
  ↓
Verificación is_active/status
  ↓
Regeneración de sesión
  ↓
Dashboard
```

### Reconstrucción

Mantener:

- autenticación segura;
- rate limiting;
- regeneración de sesión;
- invalidación de sesión al salir;
- protección CSRF;
- bloqueo de cuentas inactivas.

No mantener credenciales hardcodeadas como comportamiento de producción.

---

# 6. Dashboard

Ruta:

```text
/backend
```

Muestra:

- check-ins del día;
- check-outs del día;
- habitaciones disponibles;
- reservaciones activas;
- ingresos diarios;
- ingresos mensuales;
- alertas;
- reservas recientes;
- próximas reservas;
- módulos accesibles según permisos.

El dashboard actualmente consulta principalmente `Booking`, no `Reservation`, para varias métricas.

### Problema

Existe una inconsistencia conceptual:

```text
Dashboard → Booking
Reportes → Booking + Reservation
Calendario → Reservation
```

El sistema nuevo debe tener una sola fuente de verdad para la estancia/reserva, o definir claramente dos entidades con responsabilidades distintas.

---

# 7. Habitaciones

Entidad:

```text
rooms
```

Relacionada con:

- floor;
- room_type;
- bookings;
- room_availabilities.

## Datos

- número de habitación;
- piso;
- tipo;
- precio por noche;
- capacidad incluida;
- capacidad máxima;
- precio por persona adicional;
- estado operativo;
- estado de limpieza;
- descripción;
- imagen;
- activa/inactiva;
- smoking;
- balcón.

## Estados operativos

```text
available
occupied
reserved
maintenance
blocked
```

## Estados de limpieza

```text
clean
dirty
inspected
```

Acciones especiales:

- cambiar estado;
- cambiar estado de limpieza;
- restaurar habitación eliminada;
- comprobar disponibilidad.

La entidad utiliza Soft Deletes.

---

# 8. Pisos

Entidad:

```text
floors
```

Campos:

- name;
- number;
- description;
- is_active.

Relación:

```text
Floor 1 ─── N Rooms
```

---

# 9. Tipos de habitación

Entidad:

```text
room_types
```

Campos:

- name;
- slug;
- description;
- base_price;
- capacity;
- max_capacity;
- bed_type;
- room_size;
- amenities JSON;
- image;
- is_active.

También existe:

```text
room_pricing
```

para precios por rango/temporada.

---

# 10. Huéspedes

Entidad:

```text
guests
```

Campos:

- first_name;
- last_name;
- full_name;
- phone;
- nationality;
- document_id;
- notes;
- incident_notes;
- is_vip;
- is_frequent;
- is_blacklisted;
- is_active;
- status.

Características:

- búsqueda;
- autocomplete;
- activar/desactivar;
- blacklist;
- marcar frecuente;
- restaurar;
- historial de reservas;
- historial de pagos;
- facturas;
- total gastado;
- total de estancias.

`document_id` es único.

---

# 11. Bookings

Entidad:

```text
bookings
```

Esta es una de las dos estructuras principales de alojamiento existentes.

## Datos

- booking_number;
- guest;
- room;
- check-in;
- check-out;
- horas;
- adultos;
- niños;
- tarifa;
- descuento;
- subtotal;
- impuesto;
- total;
- pagado;
- pendiente;
- estado;
- método de pago;
- solicitudes especiales;
- motivo de cancelación;
- usuario creador;
- usuario que canceló.

## Estados

```text
pending
confirmed
checked_in
checked_out
cancelled
no_show
```

## Estados de pago

```text
unpaid
partial
paid
refunded
```

## Acciones

- crear;
- editar;
- mostrar;
- eliminar;
- check-in;
- check-out;
- cancelar;
- consultar habitaciones disponibles;
- obtener precio de habitación.

## Reglas observadas

Un booking puede:

- cancelarse si está `pending` o `confirmed`;
- hacer check-in si está `confirmed` y la fecha de check-in ya llegó;
- hacer check-out si está `checked_in`.

Número generado:

```text
BK-YYYYMMDD-XXXXX
```

### Problema importante

La generación utiliza:

```text
max(id) + 1
```

Esto no es segura ante concurrencia.

La reconstrucción debe usar una estrategia transaccional o basada en ID/UUID/sequence.

---

# 12. Reservations

Entidad:

```text
reservations
```

Es la segunda estructura de alojamiento.

Campos:

- guest_id;
- room_id;
- check_in;
- check_out;
- guests_count;
- status;
- notes.

Estados:

```text
pending
confirmed
checked_in
checked_out
cancelled
```

Tiene:

- servicios;
- eventos de calendario;
- factura;
- pagos mediante relación con factura.

## Disponibilidad

Se verifica contra otras `reservations`.

El algoritmo actual considera conflicto si los rangos se intersectan.

### Problema

La lógica de disponibilidad existe tanto para:

```text
Booking
Reservation
RoomAvailability
```

pero no existe una única política centralizada.

---

# 13. Servicios adicionales

Entidad:

```text
services
```

Campos:

- name;
- slug;
- description;
- price;
- price_type;
- unit;
- category;
- is_active;
- is_available_24h;
- available_from;
- available_to;
- icon;
- notes;
- created_by.

Puede asociarse a:

```text
booking_services
reservation_services
```

Esto vuelve a reflejar la duplicidad Booking/Reservation.

---

# 14. Facturación

Entidad:

```text
invoices
```

Puede asociarse a:

```text
booking_id
reservation_id
guest_id
```

Esto confirma que la aplicación intenta soportar ambos modelos.

Campos financieros:

- room_charges;
- service_charges;
- food_charges;
- other_charges;
- subtotal;
- discount_amount;
- tax_amount;
- total_amount;
- paid_amount;
- due_amount;
- refund_amount.

Estados:

```text
draft
pending
paid
partial
cancelled
refunded
```

Acciones:

- crear;
- editar;
- consultar;
- marcar como pagada;
- cancelar;
- generar PDF.

Número:

```text
INV-YYYYMMDD-XXXXX
```

Mismo problema de concurrencia que los bookings: se basa en `max(id)+1`.

---

# 15. Pagos

Entidad:

```text
payments
```

Campos:

- payment_number;
- invoice;
- booking;
- guest;
- amount;
- payment_date;
- payment_method;
- transaction_id;
- card_last_four;
- card_type;
- bank_name;
- reference_number;
- notes;
- status;
- created_by.

Métodos:

```text
cash
card
bank_transfer
online
credit
mixed
```

Estados:

```text
pending
completed
failed
refunded
```

Acciones:

- registrar;
- consultar;
- resumen;
- reembolsar.

Al registrar un pago:

```text
Payment
   ↓
Invoice paid_amount/due_amount/status
   ↓
Booking paid_amount/due_amount/payment_status
```

El flujo de actualización debe convertirse en una transacción atómica en la reconstrucción.

---

# 16. Reembolsos

El sistema actual:

1. cambia el pago a `refunded`;
2. resta el monto del invoice;
3. actualiza due amount;
4. actualiza el booking si existe;
5. cambia el estado correspondiente.

### Problema

El flujo no parece modelar un movimiento financiero de reversión separado.

Para una reconstrucción robusta se recomienda que un reembolso quede representado como operación financiera auditable, sin simplemente mutar el valor histórico del pago.

---

# 17. Reportes

Tres tipos:

## Operacional

Incluye:

- ocupación;
- habitaciones disponibles;
- reservaciones activas;
- resumen de estado de habitaciones;
- resumen por tipo.

## Financiero

Incluye:

- ingresos diarios;
- ingresos mensuales;
- métodos de pago;
- saldos pendientes;
- total recaudado;
- total facturado;
- total pendiente.

## Estadístico

Incluye:

- huéspedes frecuentes;
- temporadas/picos;
- tendencias de cancelación.

Filtros:

- fecha inicial;
- fecha final;
- tipo de habitación;
- estado.

Exportación:

```text
PDF
Excel
```

### Problema

Los reportes mezclan consultas sobre:

```text
Booking
Reservation
```

Por ejemplo, algunas métricas financieras consultan `Payment`, mientras otras relaciones dependen de `Reservation`.

El sistema nuevo debe definir claramente la fuente de verdad financiera y de ocupación.

---

# 18. Auditoría

Existe:

```text
audit_logs
```

Campos:

- user_id;
- module;
- action;
- record_id;
- description;
- old_values;
- new_values;
- ip_address;
- user_agent.

Además existe `AuditObserver`.

Se auditan automáticamente varios modelos:

```text
Booking
Guest
Invoice
Payment
Reservation
ReservationService
Room
Service
User
```

También existen registros explícitos para operaciones como:

- login;
- logout;
- cambios de contraseña;
- roles;
- permisos;
- operaciones de calendario.

### Reconstrucción

Mantener auditoría como una capacidad central.

Nunca guardar:

- contraseñas;
- remember tokens;
- secretos;
- credenciales.

---

# 19. Roles y permisos

## Roles

Entidad:

```text
roles
```

Campos:

- name;
- guard_name;
- slug;
- description.

## Permisos

Entidad:

```text
permissions
```

Campos:

- name;
- guard_name;
- slug;
- module;
- description.

## Relaciones

```text
users ↔ roles
roles ↔ permissions
users ↔ permissions
```

Pivotes:

```text
role_user
permission_role
permission_user
```

Además `users` tiene:

```text
role_id
```

### Problema

Hay dos representaciones de rol:

```text
users.role_id
```

y

```text
role_user
```

El nuevo sistema debe eliminar la duplicidad.

### Regla actual

El usuario con rol `admin` obtiene automáticamente todos los permisos mediante:

```text
User::hasPermission()
```

---

# 20. Autorización actual

Todas las rutas backend están dentro de:

```text
auth
sanitize.input
backend.authorize
```

El middleware `AuthorizeBackendRoute` deduce el permiso basándose en el nombre de la ruta.

Ejemplos:

```text
backend.rooms.index
→ rooms.view

backend.rooms.create
→ rooms.create

backend.rooms.edit
→ rooms.edit

backend.rooms.destroy
→ rooms.delete

backend.bookings.check_in
→ bookings.checkin

backend.bookings.check_out
→ bookings.checkout

backend.bookings.cancel
→ bookings.cancel

backend.reports.index
→ reports.view

backend.reports.export_pdf
→ reports.export

backend.settings.index
→ settings.view

backend.settings.update_general
→ settings.update
```

## Problemas confirmados del mecanismo genérico

### 20.1 Room restore

```text
backend.rooms.restore
```

cae en la regla genérica y termina utilizando el permiso por defecto de visualización, no uno específico de restauración/edición.

### 20.2 Guest restore

```text
backend.guests.restore
```

tiene el mismo problema.

### 20.3 Password update/reset

Rutas:

```text
backend.users.update_password
backend.users.reset_password
```

no terminan necesariamente en un permiso especializado. El algoritmo genérico puede resolverlas como `users.view`.

Estas operaciones deberían requerir un permiso administrativo explícito.

### 20.4 Reservation services

La ruta:

```text
backend.reservations.services.store
```

no pertenece al conjunto de recursos reconocido por el middleware como módulo independiente.

La autorización debe definirse explícitamente.

### Reconstrucción

No utilizar el nombre de la ruta como única fuente de autorización.

Usar permisos explícitos:

```text
rooms.view
rooms.create
rooms.update
rooms.delete
rooms.restore
rooms.status.update
rooms.cleaning.update
```

etc.

---

# 21. Matriz funcional de permisos recomendada

El nuevo agente debe diseñar una matriz explícita.

## Dashboard

```text
dashboard.view
```

## Rooms

```text
rooms.view
rooms.create
rooms.update
rooms.delete
rooms.restore
rooms.status.update
rooms.cleaning.update
rooms.availability.view
```

## Room types

```text
room_types.view
room_types.create
room_types.update
room_types.delete
```

## Floors

```text
floors.view
floors.create
floors.update
floors.delete
```

## Guests

```text
guests.view
guests.create
guests.update
guests.delete
guests.restore
guests.status.update
guests.blacklist.update
guests.frequent.update
```

## Reservations

```text
reservations.view
reservations.create
reservations.update
reservations.delete
reservations.confirm
reservations.cancel
reservations.checkin
reservations.checkout
reservations.availability
reservations.calendar
reservations.services.manage
```

## Billing

```text
invoices.view
invoices.create
invoices.update
invoices.delete
invoices.cancel
invoices.mark_paid
invoices.export
```

## Payments

```text
payments.view
payments.create
payments.refund
payments.summary
```

## Services

```text
services.view
services.create
services.update
services.delete
services.status.update
```

## Reports

```text
reports.view
reports.export
```

## Users

```text
users.view
users.create
users.update
users.delete
users.status.update
users.password.reset
users.roles.manage
users.permissions.manage
```

## Roles

```text
roles.view
roles.create
roles.update
roles.delete
roles.permissions.manage
```

## Permissions

```text
permissions.view
permissions.create
permissions.update
permissions.delete
```

## Settings

```text
settings.view
settings.update
settings.export
settings.import
settings.cache.clear
```

## Audit

```text
audit.view
```

---

# 22. Base de datos

## Tablas principales

La migration de aplicación crea:

```text
roles
permissions
role_user
permission_role
permission_user
floors
room_types
rooms
guests
bookings
booking_activities
services
booking_services
reservations
reservation_services
invoices
payments
room_pricing
room_availabilities
settings
audit_logs
calendar_events
```

Además las migrations base crean:

```text
users
sessions
cache
cache_locks
jobs
job_batches
failed_jobs
```

La cantidad exacta de tablas puede variar según la migración/base de datos y Laravel version.

---

# 23. Modelo relacional actual

Simplificación:

```text
User
 ├── roles
 ├── permissions
 ├── bookings.created_by
 ├── audit_logs
 └── created_by relationships

Floor
 └── Rooms

RoomType
 ├── Rooms
 └── RoomPricing

Room
 ├── Floor
 ├── RoomType
 ├── Bookings
 ├── Reservations
 └── RoomAvailabilities

Guest
 ├── Bookings
 ├── Reservations
 ├── Invoices
 └── Payments

Booking
 ├── Guest
 ├── Room
 ├── BookingServices
 ├── Invoice
 ├── Payments
 └── Activities

Reservation
 ├── Guest
 ├── Room
 ├── ReservationServices
 ├── Invoice
 ├── Payments
 └── CalendarEvents

Service
 ├── BookingServices
 └── ReservationServices

Invoice
 ├── Booking OR Reservation
 ├── Guest
 └── Payments
```

---

# 24. Problemas del modelo de datos

## 24.1 Booking vs Reservation

Es el problema arquitectónico principal.

Actualmente:

```text
Booking
Reservation
```

representan conceptos muy parecidos.

Ambos tienen:

- huésped;
- habitación;
- fechas;
- estado;
- servicios;
- facturación indirecta;
- disponibilidad.

El agente constructor debe elegir una arquitectura única.

### Recomendación de diseño

Utilizar un concepto único:

```text
Reservation
```

o un concepto único:

```text
Stay / Booking
```

y definir estados de ciclo de vida.

No mantener dos tablas paralelas salvo que exista una diferencia funcional documentada.

---

# 25. Disponibilidad

Actualmente hay tres mecanismos:

1. consultas de `Booking`;
2. consultas de `Reservation`;
3. `room_availabilities`.

Esto puede producir inconsistencias.

### Arquitectura recomendada

Crear:

```text
AvailabilityService
```

como única fuente de cálculo.

Debe recibir:

```text
room_id
check_in
check_out
exclude_reservation_id
```

y devolver:

```text
available: bool
reason: optional
```

Toda creación, edición, importación, check-in y movimiento de reserva debe pasar por esa misma lógica.

---

# 26. Precios

Existen:

```text
room_types.base_price
rooms.price_per_night
rooms.extra_person_price
room_pricing.price
room_pricing.extra_person_price
room_pricing.child_price
```

Esto crea múltiples fuentes de precio.

El nuevo sistema debe definir una jerarquía clara.

Ejemplo de arquitectura:

```text
RoomType base price
       ↓
Pricing rule/date range
       ↓
Room-specific override
       ↓
Extra guest/child pricing
       ↓
Reservation price snapshot
```

Una vez creada una reserva, el precio aplicado debe quedar almacenado como snapshot para que cambios futuros no alteren reservas históricas.

---

# 27. Impuestos

El código utiliza:

```php
config('app.tax_rate', 0.1)
```

pero `config/app.php` no define explícitamente `tax_rate`.

Por lo tanto, en las rutas observadas que utilizan esta expresión, el fallback utilizado es:

```text
10%
```

Mientras existe una pantalla de Settings que guarda `tax_rate` en la tabla `settings`.

Esto significa que el valor almacenado en Settings no está necesariamente integrado con la lógica de facturación actual.

### Problema crítico

La reconstrucción debe centralizar impuestos.

No utilizar:

```text
config('app.tax_rate', ...)
```

si el valor real vive en Settings.

Debe existir una única fuente:

```text
TaxService / BillingSettings
```

y el valor debe quedar almacenado en la factura.

---

# 28. Configuración

Existe tabla:

```text
settings
```

con pares:

```text
key
value
type
description
```

La interfaz administra:

- información del establecimiento;
- moneda;
- timezone;
- formato de fecha;
- formato de hora;
- check-in;
- check-out;
- anticipación máxima;
- estadía mínima/máxima;
- confirmación;
- pagos parciales;
- impuestos;
- cargos;
- métodos de pago;
- términos de factura;
- notificaciones;
- correo;
- configuración iCalendar.

### Reconstrucción

Crear un servicio de configuración tipado.

Evitar acceder directamente a `Setting::get()` desde todos los módulos.

---

# 29. iCalendar

El sistema tiene integración bastante completa.

Funciones:

- generar feed público;
- feed por habitación;
- exportar una reserva;
- exportar rango;
- exportar calendario completo;
- importar archivo `.ics`;
- importar desde URL;
- previsualizar eventos;
- detectar duplicados;
- detectar conflictos;
- sugerir habitación;
- importar;
- sincronizar URLs externas.

Ruta pública:

```text
/icalendar/feed/{token}
```

El token se genera y almacena en Settings.

## Seguridad

El feed valida el token usando comparación segura.

### Riesgo detectado

La aplicación realiza solicitudes HTTP a URLs proporcionadas/configuradas por el usuario para importar calendarios.

El nuevo sistema debe proteger contra:

- SSRF;
- localhost;
- IPs privadas;
- metadata endpoints;
- esquemas no permitidos;
- redirects peligrosos;
- respuestas gigantes.

También debe limitar:

- tamaño;
- timeout;
- número de eventos;
- frecuencia de sincronización.

---

# 30. Interfaz actual

## Layout

Sidebar oscuro.

Menú:

```text
Panel de Control
Reservas
Reservaciones
Calendario iCal
Habitaciones
Huéspedes
Facturas
Pagos
Servicios
Reportes
Registros de Auditoría
Usuarios
Roles
Permisos
Configuración
```

Los elementos aparecen dependiendo de permisos.

Header:

- título de página;
- botón móvil para sidebar;
- usuario actual;
- perfil;
- cerrar sesión.

Existe breadcrumb.

Existe sistema de toast:

```text
success
error
warning
```

Existe presentación global de errores de validación.

---

# 31. Login visual

La pantalla tiene:

- card centrada;
- logo textual `CE`;
- nombre Cabañas el Capitán;
- formulario username/password;
- remember me;
- botón de login;
- mensaje de rate limiting.

---

# 32. Dashboard visual

Tarjetas principales:

```text
Check-ins de Hoy
Check-outs de Hoy
Habitaciones disponibles
Reservaciones activas
```

Después:

```text
Elija un módulo
```

y cards para cada módulo permitido.

El código del controlador también prepara:

- ingresos diarios;
- ingresos mensuales;
- gráfico;
- reservas recientes;
- próximas reservas;
- alertas;

aunque la vista observada no muestra todos esos datos.

### Problema

Existe lógica de backend no necesariamente representada en la UI actual.

El nuevo agente debe eliminar datos muertos o construir una UI coherente.

---

# 33. Formularios observados

## Guest

- nombre;
- apellido;
- teléfono;
- documento;
- nacionalidad;
- notas;
- incidentes;
- VIP;
- frecuente;
- blacklist;
- activo;
- estado.

## Room

- número;
- piso;
- tipo;
- floor textual legado;
- edificio;
- precio;
- capacidad;
- capacidad máxima;
- persona adicional;
- estado;
- limpieza;
- descripción;
- activo;
- smoking;
- balcón.

## Reservation

- huésped;
- habitación;
- check-in;
- check-out;
- cantidad de huéspedes;
- estado;
- notas.

## Booking

- huésped;
- habitación;
- fechas;
- adultos;
- niños;
- tarifa;
- descuentos;
- método de pago;
- solicitudes especiales.

## Invoice

- booking/reservation;
- cargos;
- descuentos;
- fechas;
- notas;
- términos.

## Payment

- factura;
- monto;
- método;
- datos de transacción;
- banco;
- referencia;
- notas.

## Settings

Múltiples formularios agrupados por configuración.

---

# 34. Estilos

La implementación actual combina:

```text
Bootstrap 5.3 CDN
Bootstrap Icons CDN
Tailwind CSS 4
```

Esto debe simplificarse.

### Decisión recomendada

Elegir uno:

```text
Bootstrap
```

o

```text
Tailwind
```

No mantener ambos como sistemas de diseño principales.

Para una reconstrucción desde cero, definir un design system central:

```text
colors
typography
spacing
buttons
forms
cards
tables
badges
modals
alerts
navigation
responsive breakpoints
```

---

# 35. JavaScript actual

El JS global maneja:

- toast;
- loading overlay;
- sidebar responsive;
- backdrop móvil;
- accesibilidad básica del sidebar.

No existe una arquitectura JS compleja en el árbol actual.

FullCalendar se utiliza para calendario de reservas.

---

# 36. Validación

Existen Form Requests para:

- login;
- guest;
- room;
- user;
- profile;
- reservation.

Otras validaciones están directamente en controladores.

### Problema

No hay una política consistente.

El nuevo proyecto debe centralizar validaciones por caso de uso.

---

# 37. Seguridad actual

## Medidas positivas observadas

- CSRF;
- sesiones;
- regeneración de sesión;
- rate limiting de login;
- validación;
- middleware de permisos;
- sanitización de inputs;
- Blade escaped output;
- auditoría;
- comprobación de usuarios activos.

## Problemas a corregir

### 37.1 Credenciales por defecto

`DatabaseSeeder` crea un administrador con una contraseña conocida.

Esto debe sustituirse por:

- variables de entorno;
- password inicial temporal;
- cambio obligatorio;
- o creación manual.

### 37.2 Reset de contraseña

El código actual restablece la contraseña a un valor hardcodeado.

No debe existir en producción.

### 37.3 Permisos demasiado amplios

Algunas rutas sensibles heredan `view`.

Debe utilizarse autorización explícita.

### 37.4 SSRF en importación iCalendar

Debe endurecerse la importación por URL.

### 37.5 Gestión de avatar

El archivo se mueve directamente a `public/avatars`.

Debe utilizarse Storage y validación de contenido/extension de forma segura.

### 37.6 Cache flush global

Settings llama:

```text
Cache::flush()
```

Esto puede afectar datos de otros componentes de la aplicación.

Debe limpiarse únicamente el namespace/cache necesario.

---

# 38. Auditoría y privacidad

La auditoría almacena:

```text
IP
User Agent
old_values
new_values
```

Esto es útil pero puede contener datos personales.

La reconstrucción debe:

- filtrar información sensible;
- definir retención;
- limitar acceso;
- evitar almacenar secretos;
- registrar quién ejecutó la operación.

---

# 39. Problemas de consistencia documental

## README

El README declara unas credenciales predeterminadas que no coinciden con el `DatabaseSeeder` actual.

Esto debe corregirse.

## Idioma

Hay mezcla de:

```text
English
Spanish
```

en:

- nombres internos;
- comentarios;
- mensajes;
- títulos;
- README;
- interfaz.

El nuevo sistema debe elegir una convención.

Recomendación:

- UI en español;
- código interno en inglés;
- nombres de entidades consistentes.

---

# 40. Problemas de nomenclatura

Existe mezcla:

```text
Booking
Reservation
Room
RoomType
Floor
Guest
Service
```

pero además:

```text
floor
floor_id
floorLevel()
```

La columna textual:

```text
rooms.floor
```

parece ser un legado de una estructura anterior, ya que existe `floor_id`.

La reconstrucción debería eliminar el campo textual si no tiene una función real.

---

# 41. Pruebas

En el árbol `main` observado existen:

```text
tests/Feature/ExampleTest.php
tests/Unit/ExampleTest.php
```

La cobertura de las reglas de negocio críticas es insuficiente.

Debe crearse una suite para:

## Autenticación

- login correcto;
- login incorrecto;
- rate limit;
- usuario inactivo;
- logout.

## Autorización

- cada permiso;
- cada rol;
- acceso directo a rutas;
- operaciones sensibles.

## Habitaciones

- creación;
- actualización;
- estados;
- limpieza;
- disponibilidad.

## Reservas

- creación;
- conflicto;
- fechas;
- capacidad;
- cancelación;
- confirmación;
- check-in;
- check-out.

## Facturación

- cálculo;
- impuestos;
- descuento;
- saldo.

## Pagos

- pago parcial;
- pago completo;
- exceso;
- reembolso.

## iCalendar

- importación;
- exportación;
- duplicados;
- conflictos;
- token;
- SSRF.

## Reportes

- filtros;
- totales;
- exportaciones.

---

# 42. Reglas de negocio que deben conservarse

Estas reglas se observan claramente en el sistema actual y deben preservarse conceptualmente:

1. Un usuario inactivo no puede iniciar sesión.
2. El login debe tener rate limiting.
3. Una habitación no debe aceptar dos alojamientos superpuestos.
4. El check-out debe ser posterior al check-in.
5. Una reserva no debe exceder la capacidad máxima de la habitación.
6. Un pago no debe superar el saldo pendiente.
7. Un pago completo cambia la factura a pagada.
8. Los pagos parciales deben reflejar saldo pendiente.
9. Los reembolsos deben revertir el saldo financiero.
10. Las operaciones importantes deben quedar auditadas.
11. Los usuarios deben estar limitados por permisos.
12. Una factura puede asociarse a una reserva/estancia.
13. Las reservas canceladas no deben bloquear disponibilidad.
14. Los eventos iCalendar deben poder detectarse como duplicados.
15. Los conflictos de calendario deben detectarse antes de importar.
16. La disponibilidad debe tener una sola implementación en el sistema nuevo.
17. Los precios aplicados a una reserva deben conservarse aunque cambie la configuración futura.

---

# 43. Reglas que NO deben copiarse literalmente

El nuevo agente debe evitar reproducir:

1. `max(id) + 1` para generar identificadores.
2. Dos tablas paralelas `bookings` y `reservations` sin justificación.
3. `users.role_id` + `role_user` simultáneamente.
4. Autorización basada exclusivamente en nombres de rutas.
5. Password de reset hardcodeado.
6. Tax rate hardcodeado como fallback cuando existe Settings.
7. `Cache::flush()` global por cualquier cambio de Settings.
8. Múltiples mecanismos independientes de disponibilidad.
9. Dos estructuras de servicios (`booking_services` y `reservation_services`) si se unifica alojamiento.
10. Mezcla de Bootstrap y Tailwind sin un sistema de diseño definido.
11. Lógica de negocio crítica repartida sin una capa de aplicación clara.
12. Importación de URLs externas sin protección SSRF.
13. Mutaciones financieras sin transacciones.
14. Reportes que mezclen fuentes de verdad sin una política definida.
15. Datos derivados que puedan quedar desincronizados.

---

# 44. Arquitectura objetivo recomendada

La reconstrucción debe ser modular.

Propuesta:

```text
app/
├── Domain/
│   ├── Reservations/
│   ├── Rooms/
│   ├── Guests/
│   ├── Billing/
│   ├── Payments/
│   ├── Services/
│   ├── Users/
│   ├── Reports/
│   └── Calendar/
│
├── Application/
│   ├── Reservations/
│   ├── Billing/
│   ├── Payments/
│   └── ...
│
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
│
├── Models/
├── Services/
├── Policies/
├── Events/
├── Listeners/
└── Jobs/
```

No es obligatorio utilizar exactamente esta estructura, pero debe existir una separación clara entre:

```text
HTTP
Application logic
Domain/business rules
Persistence
Infrastructure
```

---

# 45. Reserva/estancia recomendada

El nuevo sistema debería tener una entidad central.

Ejemplo:

```text
reservations
```

con:

```text
id
reservation_number
guest_id
room_id
check_in
check_out
adults
children
guests_count
status
room_rate_snapshot
discount
tax
subtotal
total
paid
due
notes
created_by
cancelled_by
cancelled_at
cancellation_reason
created_at
updated_at
```

Estados:

```text
pending
confirmed
checked_in
checked_out
cancelled
no_show
```

Los servicios deben relacionarse directamente con esa entidad.

---

# 46. Facturación recomendada

Una factura debe ser un snapshot financiero.

Debe guardar:

```text
invoice_number
reservation_id
guest_id
subtotal
discount
tax
total
paid
due
status
issue_date
due_date
paid_date
created_by
```

Las líneas deberían estar normalizadas:

```text
invoice_items
```

con:

```text
description
type
quantity
unit_price
discount
tax
total
```

Esto es preferible a tener columnas rígidas:

```text
room_charges
service_charges
food_charges
other_charges
```

aunque esas categorías pueden conservarse como tipos de línea.

---

# 47. Pagos recomendados

Los pagos deben ser movimientos financieros independientes.

```text
payments
```

Cada pago:

- pertenece a una factura;
- tiene monto;
- fecha;
- método;
- referencia;
- estado;
- usuario creador.

Los reembolsos deben tener su propia operación o entidad:

```text
refunds
```

o un ledger de movimientos.

No modificar silenciosamente el histórico financiero.

---

# 48. Disponibilidad recomendada

Una única implementación:

```text
AvailabilityService
```

Ejemplo conceptual:

```text
check(room, start, end, ignoreReservation?)
```

Debe verificar:

1. habitación activa;
2. habitación no bloqueada;
3. mantenimiento;
4. reservas superpuestas;
5. reglas de disponibilidad;
6. capacidad.

Todos los módulos deben utilizarla.

---

# 49. Pricing recomendado

Crear una política:

```text
PricingService
```

Entrada:

```text
room
date_range
guests
adults
children
```

Salida:

```text
nightly_rate
extra_guest_charges
child_charges
subtotal
```

Guardar el precio final utilizado en la reserva.

---

# 50. Estados

Los estados deben centralizarse.

No utilizar strings dispersos por todo el proyecto.

Ejemplo:

```text
ReservationStatus
PaymentStatus
InvoiceStatus
RoomStatus
CleaningStatus
```

Con transiciones controladas.

Ejemplo:

```text
pending → confirmed
confirmed → checked_in
checked_in → checked_out

pending → cancelled
confirmed → cancelled
```

Una transición inválida debe rechazarse.

---

# 51. Transacciones

Deben utilizarse transacciones para:

## Crear reserva

```text
crear reserva
+ reservar disponibilidad
+ crear servicios
+ crear auditoría
```

## Registrar pago

```text
crear payment
+ actualizar invoice
+ actualizar reservation financial status
+ audit
```

## Reembolso

```text
crear refund
+ actualizar invoice
+ actualizar payment status/ledger
+ actualizar balance
+ audit
```

## Check-in

```text
validar estado
+ validar fecha
+ cambiar estado
+ actualizar habitación
+ audit
```

---

# 52. Dashboard objetivo

El nuevo dashboard debería tener:

## Operación

- check-ins hoy;
- check-outs hoy;
- huéspedes alojados;
- habitaciones ocupadas;
- habitaciones disponibles;
- habitaciones en limpieza;
- habitaciones bloqueadas.

## Reservas

- próximas;
- pendientes;
- confirmadas;
- canceladas.

## Finanzas

- ingresos del día;
- ingresos del mes;
- saldo pendiente;
- pagos recientes.

## Alertas

- check-ins pendientes;
- check-outs pendientes;
- facturas vencidas;
- habitaciones con problemas.

Todos los datos deben provenir de una misma arquitectura de dominio.

---

# 53. Navegación objetivo

Propuesta:

```text
Dashboard

Operación
├── Reservas
├── Calendario
├── Habitaciones
├── Pisos
└── Tipos de habitación

Clientes
└── Huéspedes

Servicios
└── Servicios adicionales

Finanzas
├── Facturas
└── Pagos

Reportes
├── Operacional
├── Financiero
└── Estadístico

Administración
├── Usuarios
├── Roles
├── Permisos
├── Auditoría
└── Configuración
```

Esto evita presentar `Bookings` y `Reservations` como dos módulos distintos si finalmente se unifican.

---

# 54. Diseño UI objetivo

Mantener la intención general de la aplicación:

- dashboard administrativo;
- sidebar;
- navegación por módulos;
- cards;
- tablas;
- formularios;
- badges de estados;
- responsive;
- mensajes de éxito/error;
- breadcrumbs;
- calendario visual.

Pero reconstruir el diseño mediante un único sistema visual.

Debe incluir:

```text
Primary
Secondary
Success
Warning
Danger
Info
Neutral
```

Estados deben ser visualmente distinguibles pero también accesibles sin depender exclusivamente del color.

---

# 55. Responsive

Debe funcionar correctamente en:

```text
mobile
tablet
desktop
```

La navegación móvil actual utiliza sidebar colapsable.

Debe conservarse el concepto pero mejorarse:

- focus management;
- keyboard navigation;
- ARIA;
- cierre con Escape;
- overlay;
- scroll locking.

---

# 56. Accesibilidad

La reconstrucción debe incluir:

- labels reales;
- focus visible;
- navegación por teclado;
- aria-label donde sea necesario;
- mensajes de error asociados a campos;
- contraste adecuado;
- estados no dependientes únicamente del color;
- botones semánticos;
- tablas con encabezados.

---

# 57. API

El árbol `main` expone principalmente rutas web.

No existe una API pública separada relevante en la rama activa.

La rama `feature/refactor-pendientes` sí contiene una API V1:

```text
Api/V1/BookingController
Api/V1/ReservationController
```

Esto NO debe asumirse como API actualmente disponible.

Si el nuevo sistema necesita API, debe diseñarse desde cero con:

```text
/api/v1
```

y autorización propia.

---

# 58. Reportes y exportaciones

PDF:

```text
DomPDF
```

Excel:

```text
PhpSpreadsheet
```

El nuevo sistema debe mantener exportaciones pero separar:

```text
ReportQuery
ReportPresenter
ReportExporter
```

No mezclar consultas y formato PDF/Excel dentro del mismo controlador.

---

# 59. iCalendar objetivo

Mantener:

- feed general;
- feed por habitación;
- exportación;
- importación;
- sincronización;
- detección de duplicados;
- detección de conflictos.

Mejorar:

- seguridad de URLs;
- límites;
- logs;
- sincronización incremental;
- manejo de errores;
- timezones;
- UID estable;
- mapeo explícito de habitaciones.

---

# 60. Migraciones

Actualmente hay una migration grande:

```text
0001_01_01_000003_create_application_schema.php
```

que concentra gran parte del modelo.

Para el nuevo proyecto es preferible dividir migrations por dominio o por entidad.

Ejemplo:

```text
create_roles_table
create_permissions_table
create_rooms_table
create_room_types_table
create_guests_table
create_reservations_table
create_reservation_services_table
create_invoices_table
create_invoice_items_table
create_payments_table
create_audit_logs_table
...
```

Ventajas:

- historial claro;
- rollback específico;
- mantenimiento;
- evolución controlada.

---

# 61. Seeds

El sistema actual crea un admin automáticamente.

La reconstrucción debe separar:

```text
development seed
production bootstrap
```

Nunca incluir credenciales de producción conocidas.

---

# 62. Configuración de entorno

El ZIP contiene `.env`.

El nuevo proyecto NO debe versionar secretos.

Debe existir:

```text
.env.example
```

sin:

- passwords reales;
- API keys;
- DB credentials;
- SMTP credentials;
- AWS secrets;
- application keys reales.

---

# 63. Archivos que no deben formar parte del repositorio fuente

El ZIP contiene:

```text
node_modules/
vendor/
.git/
public/build/
```

El nuevo proyecto debe poder reconstruirse mediante:

```text
composer install
npm install
npm run build
php artisan migrate
```

No debe depender de binarios o dependencias vendorizadas accidentalmente.

---

# 64. Flujo de reconstrucción recomendado

El agente constructor debe seguir este orden:

## Fase 1 — Base

- Laravel;
- configuración;
- base de datos;
- autenticación.

## Fase 2 — Seguridad

- usuarios;
- roles;
- permisos;
- policies;
- middleware.

## Fase 3 — Catálogos

- pisos;
- tipos;
- habitaciones;
- servicios.

## Fase 4 — Clientes

- huéspedes;
- historial.

## Fase 5 — Reservas

- disponibilidad;
- pricing;
- estados;
- calendario.

## Fase 6 — Finanzas

- facturas;
- líneas;
- pagos;
- reembolsos.

## Fase 7 — Reportes

- operativos;
- financieros;
- estadísticos;
- PDF;
- Excel.

## Fase 8 — Integraciones

- iCalendar;
- sincronización.

## Fase 9 — Auditoría

- eventos;
- historial;
- seguridad.

## Fase 10 — UI/UX

- dashboard;
- navegación;
- formularios;
- responsive;
- accesibilidad.

## Fase 11 — Tests

- unit;
- feature;
- authorization;
- business rules;
- integrations.

---

# 65. Checklist mínimo para considerar terminada la reconstrucción

## Auth

- [ ] login
- [ ] logout
- [ ] rate limit
- [ ] usuarios activos/inactivos
- [ ] sesión segura

## Authorization

- [ ] roles
- [ ] permisos
- [ ] policies
- [ ] middleware
- [ ] autorización por operación
- [ ] tests de acceso

## Rooms

- [ ] floors
- [ ] room types
- [ ] rooms
- [ ] cleaning status
- [ ] maintenance
- [ ] availability

## Guests

- [ ] CRUD
- [ ] búsqueda
- [ ] historial
- [ ] blacklist
- [ ] frequent/VIP

## Reservations

- [ ] create
- [ ] update
- [ ] confirm
- [ ] cancel
- [ ] check-in
- [ ] check-out
- [ ] availability
- [ ] capacity
- [ ] calendar

## Services

- [ ] CRUD
- [ ] association with reservation
- [ ] pricing
- [ ] totals

## Billing

- [ ] invoice
- [ ] invoice items
- [ ] discounts
- [ ] tax
- [ ] balance
- [ ] PDF

## Payments

- [ ] create
- [ ] partial
- [ ] full
- [ ] refund
- [ ] reconciliation

## Reports

- [ ] operational
- [ ] financial
- [ ] statistical
- [ ] filters
- [ ] PDF
- [ ] Excel

## iCalendar

- [ ] export
- [ ] import
- [ ] sync
- [ ] conflict detection
- [ ] duplicate detection
- [ ] token security
- [ ] SSRF protection

## Audit

- [ ] CRUD audit
- [ ] auth audit
- [ ] financial audit
- [ ] permission audit
- [ ] sensitive data filtering

---

# 66. Criterios de aceptación

El sistema reconstruido debe cumplir:

### Arquitectura

- [ ] no existen dos entidades duplicadas para el mismo concepto sin justificación;
- [ ] la lógica de negocio crítica está centralizada;
- [ ] los controladores son delgados;
- [ ] las transacciones financieras son atómicas.

### Base de datos

- [ ] relaciones claras;
- [ ] constraints;
- [ ] índices;
- [ ] foreign keys;
- [ ] no existen columnas legacy sin propósito.

### Seguridad

- [ ] ningún password hardcodeado;
- [ ] permisos explícitos;
- [ ] autorización server-side;
- [ ] CSRF;
- [ ] rate limiting;
- [ ] protección SSRF;
- [ ] auditoría.

### Finanzas

- [ ] cálculos reproducibles;
- [ ] impuestos configurables;
- [ ] snapshots;
- [ ] pagos reconciliables;
- [ ] reembolsos auditables.

### UX

- [ ] responsive;
- [ ] accesible;
- [ ] estados claros;
- [ ] errores comprensibles;
- [ ] navegación consistente.

### Testing

- [ ] reglas de negocio críticas cubiertas;
- [ ] autorización cubierta;
- [ ] disponibilidad cubierta;
- [ ] pagos y reembolsos cubiertos;
- [ ] importación/exportación cubierta.

---

# 67. Hallazgos críticos priorizados

## Crítico

### C1 — Duplicidad Booking/Reservation

Afecta:

- disponibilidad;
- reportes;
- servicios;
- facturación;
- dashboard;
- calendario.

Debe resolverse antes de reconstruir.

### C2 — Autorización basada en ruta

Puede asignar permisos incorrectos a acciones sensibles.

Debe reemplazarse por autorización explícita.

### C3 — Impuesto

La lógica usa fallback de 10% mientras existe un setting independiente.

Debe centralizarse.

### C4 — Password reset hardcodeado

Debe eliminarse.

### C5 — SSRF en iCalendar

Debe protegerse la importación desde URL.

---

# 68. Hallazgos importantes

## I1 — IDs generados con max(id)+1

Riesgo de colisión concurrente.

## I2 — Roles duplicados

`role_id` y pivote.

## I3 — Disponibilidad duplicada

Booking/Reservation/RoomAvailability.

## I4 — Precio duplicado

RoomType/Room/RoomPricing.

## I5 — Bootstrap + Tailwind

Sistema visual inconsistente.

## I6 — Código backend muerto

El dashboard prepara información que no se presenta completamente.

## I7 — Documentación desactualizada

README y Seeder no coinciden.

## I8 — Tests insuficientes

La rama `main` carece de pruebas reales de negocio.

---

# 69. Decisiones que el nuevo agente debe tomar antes de escribir código

No comenzar la implementación hasta resolver:

1. ¿`Booking` y `Reservation` serán una sola entidad?
2. ¿Cuál es la fuente de verdad de disponibilidad?
3. ¿Cuál es la fuente de verdad de precios?
4. ¿Cuál es la fuente de verdad de impuestos?
5. ¿Cómo se manejarán reembolsos?
6. ¿Qué permisos exactos existen?
7. ¿Qué rol tiene cada permiso?
8. ¿Bootstrap o Tailwind?
9. ¿Blade puro o Blade + componentes JS?
10. ¿Se necesita API?
11. ¿Qué datos son obligatorios para un huésped?
12. ¿Cómo se identifican reservas provenientes de iCalendar?
13. ¿Qué datos externos pueden entrar al sistema?
14. ¿Qué información debe aparecer en auditoría?
15. ¿Cuál es la política de retención de auditoría?

---

# 70. Instrucción final para el agente constructor

Este documento debe tratarse como una **especificación de comportamiento y auditoría**, no como una instrucción para copiar la implementación actual.

El agente constructor debe:

1. estudiar todo este documento;
2. identificar contradicciones;
3. resolverlas mediante una arquitectura única;
4. reconstruir desde cero;
5. no copiar errores conocidos;
6. mantener las funcionalidades de negocio verificadas;
7. mejorar seguridad;
8. centralizar reglas de negocio;
9. crear pruebas antes o junto con cada módulo;
10. verificar que la UI represente realmente los datos calculados;
11. documentar cualquier decisión donde la especificación actual sea ambigua;
12. no inventar funcionalidades no descritas aquí sin marcarlas como una decisión de diseño.

## Regla de oro

```text
COMPORTAMIENTO OBSERVADO
        ↓
REGLA DE NEGOCIO
        ↓
ARQUITECTURA LIMPIA
        ↓
IMPLEMENTACIÓN NUEVA
        ↓
TEST
```

No:

```text
CÓDIGO ACTUAL
   ↓
COPIAR
   ↓
CAMBIAR NOMBRES
```

---

# 71. Fuente de verdad de esta auditoría

La información de este documento fue obtenida inspeccionando directamente el árbol de código, migraciones, rutas, modelos, controladores, middleware, servicios, vistas, configuración, documentación y estado Git contenidos en `CEC.zip`.

No se debe asumir que una funcionalidad existe únicamente porque aparece mencionada en README/documentación. Cuando documentación y código difieren, el comportamiento del código actual fue considerado el comportamiento observado y la diferencia fue registrada como inconsistencia.

No se han incluido secretos del `.env` en este documento.
---

# 72. AJUSTES DEFINITIVOS PARA LA RECONSTRUCCION

> **Esta seccion tiene prioridad sobre cualquier apartado anterior cuando se hable del sistema NUEVO.**
>
> Los apartados anteriores conservan valor como auditoria del sistema existente. Sin embargo, el proyecto reconstruido debe aplicar obligatoriamente las decisiones de esta seccion.

## 72.1 El alojamiento se modela como cabanas, no como habitaciones de un edificio

Cabanas El Capitan no debe modelarse como un hotel compuesto por edificios, pisos y habitaciones.

Cada alojamiento es una **cabana independiente**.

Por lo tanto, en el sistema nuevo debe existir un unico modulo principal:

```text
Cabanas
```

No deben existir como conceptos funcionales:

```text
Pisos
Habitaciones
Edificios
```

Tampoco debe mantenerse una jerarquia como:

```text
Edificio
  -> Piso
      -> Habitacion
```

La relacion correcta es:

```text
Cabana
  -> Reservas
  -> Servicios asociados a sus reservas
  -> Disponibilidad
  -> Precios
  -> Calendario
```

## 72.2 Datos eliminados de cabanas

NO deben reconstruirse estos campos heredados del sistema anterior:

```text
numero
piso
floor
edificio
estado_limpieza
limpieza
smoking
balcon
```

La limpieza se administra fuera del sistema y, por tanto:

- no debe existir un modulo de limpieza;
- no debe existir `estado_limpieza`;
- no debe haber acciones para marcar una cabana como limpia, sucia o inspeccionada;
- no debe aparecer limpieza en dashboard, reportes, filtros ni permisos.

## 72.3 Modelo objetivo de cabanas

La entidad central debe llamarse:

```text
Cabana
```

Tabla:

```text
cabanas
```

Estructura base propuesta:

```text
id
nombre
slug
descripcion
capacidad_base
capacidad_maxima
precio_base
precio_persona_adicional
estado
activa
imagen_principal
notas
creado_por
created_at
updated_at
deleted_at
```

Los campos exactos pueden ampliarse cuando una funcionalidad descrita en esta especificacion lo requiera, pero NO deben reintroducirse conceptos de edificio, piso, habitacion o limpieza.

Estados operativos recomendados:

```text
disponible
ocupada
reservada
mantenimiento
bloqueada
```

Los estados deben implementarse internamente sin tildes.

## 72.4 Listado de cabanas

Debe existir una sola pantalla principal:

```text
Cabanas
```

Debe permitir como minimo:

- listar cabanas;
- buscar;
- ver detalle;
- crear;
- editar;
- activar/desactivar;
- cambiar estado operativo;
- consultar disponibilidad;
- eliminar logicamente;
- restaurar cuando el usuario tenga permiso.

No crear pantallas separadas para pisos ni tipos de habitacion salvo que posteriormente exista una necesidad real del negocio.

## 72.5 Precios de cabanas

El precio pertenece directamente al concepto de cabana y a sus reglas de precio.

Modelo conceptual:

```text
Cabana
   -> precio_base
   -> precio_persona_adicional
   -> reglas_precio
```

Si se necesitan precios variables por fechas:

```text
reglas_precio_cabana
```

Ejemplo:

```text
id
cabana_id
fecha_inicio
fecha_fin
precio
precio_persona_adicional
activa
```

Al crear una reserva, los valores utilizados deben almacenarse como snapshot en la propia reserva para preservar el historico.

---

# 73. IDIOMA Y NOMENCLATURA OBLIGATORIA

Todo el proyecto nuevo debe construirse en **espanol**.

Esto aplica a:

- nombres de modulos;
- variables propias del proyecto;
- clases de dominio;
- servicios propios;
- controladores;
- Form Requests propios;
- modelos;
- tablas;
- columnas;
- rutas nombradas;
- permisos;
- estados;
- mensajes;
- labels;
- botones;
- menus;
- documentacion;
- comentarios cuando existan;
- nombres de pruebas relacionadas con el negocio.

## 73.1 Regla de caracteres

Los identificadores tecnicos deben escribirse:

```text
en español
sin tildes
sin ñ
```

Ejemplos:

```text
cabana
cabanas
reservacion
reservaciones
huesped
huespedes
factura
facturas
pago
pagos
servicio
servicios
usuario
usuarios
rol
roles
permiso
permisos
configuracion
auditoria
disponibilidad
```

En texto visible al usuario SI deben utilizarse correctamente los caracteres del idioma:

```text
Cabañas
Reservación
Huésped
Configuración
```

La restriccion de no utilizar tildes o `ñ` aplica a identificadores tecnicos, no al texto presentado en pantalla.

## 73.2 Convenciones Laravel/framework

No se deben renombrar componentes internos que Laravel espera por convencion cuando hacerlo rompa compatibilidad.

Por ejemplo, pueden conservarse columnas/framework como:

```text
id
created_at
updated_at
deleted_at
remember_token
```

y archivos/configuraciones propios de Laravel.

La regla de español aplica al codigo de negocio creado para este proyecto.

---

# 74. NOMENCLATURA OBJETIVO

Utilizar los siguientes nombres como base.

| Concepto visible | Codigo | Tabla |
|---|---|---|
| Cabana | `Cabana` | `cabanas` |
| Huesped | `Huesped` | `huespedes` |
| Reservacion | `Reservacion` | `reservaciones` |
| Servicio | `Servicio` | `servicios` |
| Factura | `Factura` | `facturas` |
| Detalle de factura | `DetalleFactura` | `detalles_factura` |
| Pago | `Pago` | `pagos` |
| Reembolso | `Reembolso` | `reembolsos` |
| Usuario | `Usuario` | `usuarios` |
| Rol | `Rol` | `roles` |
| Permiso | `Permiso` | `permisos` |
| Configuracion | `Configuracion` | `configuraciones` |
| Registro de auditoria | `RegistroAuditoria` | `registros_auditoria` |
| Regla de precio | `ReglaPrecioCabana` | `reglas_precio_cabana` |
| Evento de calendario | `EventoCalendario` | `eventos_calendario` |

No crear simultaneamente nombres equivalentes en ingles.

---

# 75. RESERVACIONES COMO UNICA FUENTE DE VERDAD

La reconstruccion NO debe crear nuevamente:

```text
bookings
reservations
```

como entidades paralelas.

Debe existir una unica entidad:

```text
Reservacion
```

Tabla:

```text
reservaciones
```

Esta entidad representa todo el ciclo desde la reservacion hasta la salida del huesped.

Estados:

```text
pendiente
confirmada
hospedada
finalizada
cancelada
no_presentado
```

Si se desea conservar una nomenclatura mas cercana al flujo anterior, pueden utilizarse:

```text
pendiente
confirmada
check_in
check_out
cancelada
no_presentado
```

pero debe elegirse una sola convencion y aplicarla en todo el sistema.

La entidad debe relacionarse directamente con:

```text
huesped
cabana
servicios
factura
pagos
calendario
auditoria
```

---

# 76. DISPONIBILIDAD DE CABANAS

Debe existir una unica fuente de verdad para disponibilidad.

Servicio sugerido:

```text
ServicioDisponibilidad
```

Operacion conceptual:

```text
verificar(cabana, fecha_entrada, fecha_salida, reservacion_excluida)
```

Debe considerar:

1. que la cabana este activa;
2. que no este bloqueada;
3. que no este en mantenimiento;
4. que no exista una reservacion incompatible;
5. reglas manuales de disponibilidad si se implementan;
6. capacidad maxima.

No debe existir disponibilidad separada para `booking`, `reservation` y `room`.

---

# 77. PERMISOS EN ESPANOL

Ejemplo de permisos objetivo:

```text
panel.ver

cabanas.ver
cabanas.crear
cabanas.editar
cabanas.eliminar
cabanas.restaurar
cabanas.cambiar_estado
cabanas.ver_disponibilidad

huespedes.ver
huespedes.crear
huespedes.editar
huespedes.eliminar
huespedes.restaurar
huespedes.cambiar_estado
huespedes.cambiar_lista_negra
huespedes.marcar_frecuente

reservaciones.ver
reservaciones.crear
reservaciones.editar
reservaciones.eliminar
reservaciones.confirmar
reservaciones.cancelar
reservaciones.registrar_entrada
reservaciones.registrar_salida
reservaciones.ver_disponibilidad
reservaciones.ver_calendario
reservaciones.gestionar_servicios

facturas.ver
facturas.crear
facturas.editar
facturas.eliminar
facturas.cancelar
facturas.marcar_pagada
facturas.exportar

pagos.ver
pagos.crear
pagos.reembolsar
pagos.ver_resumen

servicios.ver
servicios.crear
servicios.editar
servicios.eliminar
servicios.cambiar_estado

reportes.ver
reportes.exportar

usuarios.ver
usuarios.crear
usuarios.editar
usuarios.eliminar
usuarios.cambiar_estado
usuarios.restablecer_contrasena
usuarios.gestionar_roles
usuarios.gestionar_permisos

roles.ver
roles.crear
roles.editar
roles.eliminar
roles.gestionar_permisos

permisos.ver
permisos.crear
permisos.editar
permisos.eliminar

configuracion.ver
configuracion.editar
configuracion.exportar
configuracion.importar
configuracion.limpiar_cache

auditoria.ver
```

No generar permisos relacionados con:

```text
pisos
habitaciones
limpieza
edificios
```

---

# 78. NAVEGACION DEFINITIVA

La navegacion principal objetivo sera:

```text
Panel

Operacion
├── Reservaciones
├── Calendario
└── Cabanas

Clientes
└── Huespedes

Servicios
└── Servicios adicionales

Finanzas
├── Facturas
└── Pagos

Reportes
├── Operacional
├── Financiero
└── Estadistico

Administracion
├── Usuarios
├── Roles
├── Permisos
├── Auditoria
└── Configuracion
```

No mostrar:

```text
Habitaciones
Pisos
Tipos de habitacion
Limpieza
```

---

# 79. DASHBOARD DEFINITIVO

El dashboard debe utilizar la terminologia del negocio.

Indicadores operativos:

```text
Entradas de hoy
Salidas de hoy
Huespedes alojados
Cabanas ocupadas
Cabanas disponibles
Cabanas reservadas
Cabanas en mantenimiento
Cabanas bloqueadas
```

Reservaciones:

```text
Proximas
Pendientes
Confirmadas
Canceladas
```

Finanzas:

```text
Ingresos del dia
Ingresos del mes
Saldo pendiente
Pagos recientes
```

Alertas:

```text
Entradas pendientes
Salidas pendientes
Facturas vencidas
Cabanas con bloqueo o mantenimiento
```

No incluir indicadores de limpieza.

---

# 80. FORMULARIO OBJETIVO DE CABANA

El formulario de cabana NO debe contener:

```text
numero
piso
tipo de habitacion
floor
edificio
limpieza
smoking
balcon
```

Debe centrarse en la unidad real de alojamiento.

Base propuesta:

```text
Nombre
Descripcion
Capacidad base
Capacidad maxima
Precio base
Precio por persona adicional
Estado
Activa
Imagen principal
Notas
```

Los campos adicionales solo deben agregarse si representan informacion real de las cabanas y tienen uso funcional.

---

# 81. MODELO RELACIONAL OBJETIVO ACTUALIZADO

```text
Usuario
 ├── Roles
 ├── Permisos
 └── RegistrosAuditoria

Cabana
 ├── Reservaciones
 ├── ReglasPrecioCabana
 └── EventosCalendario

Huesped
 ├── Reservaciones
 ├── Facturas
 └── Pagos

Reservacion
 ├── Huesped
 ├── Cabana
 ├── Servicios
 ├── Factura
 └── EventosCalendario

Servicio
 └── Reservaciones

Factura
 ├── Reservacion
 ├── Huesped
 ├── DetallesFactura
 └── Pagos

Pago
 ├── Factura
 └── Reembolsos
```

---

# 82. ESTRUCTURA DE CODIGO OBJETIVO EN ESPANOL

Ejemplo:

```text
app/
├── Dominio/
│   ├── Reservaciones/
│   ├── Cabanas/
│   ├── Huespedes/
│   ├── Facturacion/
│   ├── Pagos/
│   ├── Servicios/
│   ├── Usuarios/
│   ├── Reportes/
│   └── Calendario/
│
├── Aplicacion/
│   ├── Reservaciones/
│   ├── Facturacion/
│   ├── Pagos/
│   └── ...
│
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
│
├── Models/
├── Services/
├── Policies/
├── Events/
├── Listeners/
└── Jobs/
```

Cuando Laravel o Composer dependan de nombres convencionales, se conservan esas carpetas/framework. Dentro de ellas, los componentes de negocio deben usar español sin tildes.

Ejemplos:

```text
CabanaController
ReservacionController
HuespedController
FacturaController
PagoController

CrearCabanaRequest
ActualizarCabanaRequest
CrearReservacionRequest

ServicioDisponibilidad
ServicioPrecios
ServicioFacturacion
```

---

# 83. BASE DE DATOS OBJETIVO EN ESPANOL

El esquema nuevo debe diseñarse desde cero.

Tablas de negocio propuestas:

```text
usuarios
roles
permisos
rol_usuario
permiso_rol
permiso_usuario

cabanas
reglas_precio_cabana

huespedes

reservaciones
reservacion_servicio

servicios

facturas
detalles_factura
pagos
reembolsos

configuraciones
registros_auditoria
eventos_calendario
```

No crear:

```text
rooms
room_types
floors
bookings
reservations
booking_services
reservation_services
```

La aplicacion nueva no debe mantener tablas duplicadas solo para conservar compatibilidad con la base antigua.

La migracion de datos, si posteriormente se requiere, debe desarrollarse como proceso separado.

---

# 84. REGLA PARA COLUMNAS

Ejemplos:

```text
cabana_id
huesped_id
reservacion_id
factura_id
usuario_id
servicio_id

fecha_entrada
fecha_salida
cantidad_huespedes
adultos
ninos

precio_base
precio_persona_adicional
subtotal
descuento
impuesto
total
monto_pagado
saldo_pendiente

estado
activa
notas
creado_por
cancelado_por
fecha_cancelacion
motivo_cancelacion
```

Evitar columnas de negocio como:

```text
room_id
guest_id
booking_id
reservation_id
invoice_id
payment_status
cleaning_status
```

---

# 85. DOCUMENTO OPERATIVO PARA EL AGENTE CONSTRUCTOR

A partir de esta seccion, el contenido puede utilizarse directamente como instruccion inicial para un agente de desarrollo.

## MISION

Construye desde cero un sistema Laravel para **Cabanas El Capitan**, utilizando una instalacion/plantilla limpia de Laravel.

No reutilices el codigo del proyecto anterior.

El archivo `SYSTEM_SPEC.md` contiene una auditoria del sistema legado y las reglas funcionales que deben preservarse.

Las secciones **72 en adelante tienen prioridad** cuando exista cualquier contradiccion con apartados anteriores.

## OBJETIVO

Crear una aplicacion nueva, mantenible, segura y coherente que sustituya al sistema legado.

Debes reconstruir las funcionalidades necesarias, no copiar su arquitectura.

## REGLAS INNEGOCIABLES

1. Todo el codigo de negocio debe usar español sin tildes ni `ñ`.
2. La interfaz visible debe estar correctamente escrita en español y puede utilizar tildes y `ñ`.
3. El alojamiento se modela exclusivamente mediante `Cabana`.
4. No existen pisos.
5. No existen edificios.
6. No existe un modulo de habitaciones separado.
7. No existe gestion de limpieza.
8. No existen campos `smoking` ni `balcon` por defecto.
9. Existe una sola entidad `Reservacion`.
10. No crear `Booking` y `Reservation` paralelos.
11. Existe una sola fuente de disponibilidad.
12. Existe una sola fuente de precios.
13. Existe una sola fuente de impuestos.
14. Los permisos son explicitos por accion.
15. No deducir permisos automaticamente del nombre de una ruta.
16. No hardcodear contraseñas.
17. No hardcodear impuestos.
18. No generar consecutivos mediante `max(id)+1`.
19. Las operaciones financieras deben ser transaccionales.
20. Los reembolsos deben quedar auditables.
21. La importacion iCalendar debe protegerse contra SSRF.
22. No versionar secretos.
23. Crear pruebas para reglas de negocio criticas.
24. No implementar una funcionalidad inventada sin documentarla como decision.
25. No intentar mantener compatibilidad estructural con la base de datos antigua a costa de ensuciar el nuevo modelo.

## FORMA DE TRABAJO

Antes de implementar un modulo:

1. identifica las reglas de negocio aplicables;
2. define las tablas necesarias;
3. define permisos;
4. define validaciones;
5. define casos de uso;
6. implementa;
7. crea pruebas;
8. verifica UI y autorizacion;
9. documenta decisiones relevantes.

No construyas toda la aplicacion en un unico controlador ni concentres todo el esquema en una sola migration.

## ORDEN DE IMPLEMENTACION

### Etapa 1 — Fundacion

Crear:

- proyecto Laravel limpio;
- `.env.example`;
- configuracion de base de datos;
- autenticacion;
- layout base;
- sistema de errores;
- logging.

### Etapa 2 — Usuarios y seguridad

Crear:

- usuarios;
- roles;
- permisos;
- asignacion de roles;
- asignacion de permisos;
- policies/middleware;
- auditoria de autenticacion.

### Etapa 3 — Cabanas

Crear:

- tabla `cabanas`;
- modelo `Cabana`;
- CRUD;
- estados;
- activacion/desactivacion;
- soft delete;
- restauracion;
- imagen;
- permisos;
- pruebas.

No crear pisos, edificios ni limpieza.

### Etapa 4 — Huespedes

Crear:

- `huespedes`;
- CRUD;
- busqueda;
- historial;
- VIP;
- frecuente;
- lista negra;
- permisos;
- pruebas.

### Etapa 5 — Reservaciones

Crear:

- `reservaciones`;
- estados;
- disponibilidad;
- capacidad;
- precios snapshot;
- confirmacion;
- cancelacion;
- entrada;
- salida;
- no presentado;
- permisos;
- auditoria;
- pruebas.

### Etapa 6 — Servicios adicionales

Crear:

- `servicios`;
- `reservacion_servicio`;
- precios;
- cantidades;
- totales;
- permisos;
- pruebas.

### Etapa 7 — Facturacion

Crear:

- `facturas`;
- `detalles_factura`;
- impuestos;
- descuentos;
- snapshots;
- PDF;
- permisos;
- pruebas.

### Etapa 8 — Pagos

Crear:

- `pagos`;
- pagos parciales;
- pagos completos;
- saldo;
- metodos;
- referencias;
- reembolsos;
- transacciones;
- auditoria;
- pruebas.

### Etapa 9 — Calendario

Crear:

- calendario visual;
- eventos por reservacion;
- disponibilidad;
- iCalendar;
- importacion;
- exportacion;
- feeds;
- seguridad SSRF;
- pruebas.

### Etapa 10 — Reportes

Crear:

- operacional;
- financiero;
- estadistico;
- filtros;
- PDF;
- Excel;
- permisos;
- pruebas.

### Etapa 11 — Configuracion

Crear:

- datos del establecimiento;
- moneda;
- zona horaria;
- horarios de entrada/salida;
- impuestos;
- pagos;
- notificaciones;
- correo;
- iCalendar.

### Etapa 12 — Dashboard

Crear usando exclusivamente las fuentes nuevas:

- entradas de hoy;
- salidas de hoy;
- cabanas disponibles;
- cabanas ocupadas;
- reservaciones;
- ingresos;
- saldos;
- alertas.

## NO HACER

No:

- copies controladores antiguos;
- copies migrations antiguas;
- copies modelos antiguos;
- copies la duplicidad Booking/Reservation;
- agregues `floors`;
- agregues `room_types` solo porque existen en el legado;
- agregues estados de limpieza;
- agregues `building`;
- agregues `smoking`;
- agregues `balcony`;
- mezcles nombres de negocio ingles/español;
- uses una contraseña fija de restablecimiento;
- permitas operaciones administrativas con un simple permiso `ver`;
- hagas llamadas arbitrarias a URLs privadas durante sincronizacion iCalendar.

## CRITERIO PARA DUDAS

Si una parte del sistema legado contradice esta especificacion:

```text
secciones 72+ de SYSTEM_SPEC.md
        ↓
reglas de negocio verificadas
        ↓
comportamiento legado
        ↓
implementacion antigua
```

Ese es el orden de prioridad.

Si aun existe una ambiguedad que afecte datos, seguridad, dinero o reglas de negocio, NO inventes la respuesta: documenta la duda y solicita una decision antes de implementar esa parte.

## DEFINICION DE TERMINADO

Una funcionalidad no esta terminada solo porque la pantalla funciona.

Debe tener:

```text
migracion
modelo
reglas
validacion
autorizacion
caso de uso
interfaz
manejo de errores
auditoria cuando aplique
pruebas
```

## RESULTADO ESPERADO

El resultado final debe ser un sistema nuevo que represente realmente la operacion de **Cabanas El Capitan**, sin heredar la estructura hotelera incorrecta del sistema anterior.

---

# 86. BUSQUEDA Y SELECCION DE CLIENTES PARA NUEVAS OPERACIONES

> **Requisito adicional aprobado para la reconstruccion.** Esta seccion precisa la busqueda de huespedes y su reutilizacion en formularios. No aprueba las demas mejoras propuestas fuera de este documento ni modifica las reglas financieras existentes.

## 86.1 Objetivo y entidad utilizada

Permitir encontrar un cliente existente por su nombre, numero de telefono o numero de identidad al iniciar una nueva reservacion, factura o movimiento que requiera seleccionar un cliente.

El cliente corresponde al registro existente de `Huesped`, en la tabla `huespedes`. No crear otra entidad `Cliente`, otra tabla `clientes` ni copiar al huesped para cada operacion.

El termino movimiento se refiere a las operaciones ya contempladas por la especificacion. Esta mejora no crea un modulo generico de movimientos ni una nueva estructura financiera.

## 86.2 Componente compartido

Crear un componente de busqueda y seleccion reutilizable en las vistas Blade de las operaciones aplicables, junto con una unica implementacion de consulta en el servidor.

Nombres de negocio sugeridos:

```text
BuscadorHuesped
ServicioBusquedaHuespedes
buscarHuespedes
termino_busqueda
huesped_seleccionado
huesped_id
```

El campo debe permitir escribir cualquiera de los tres criterios sin cambiar de pantalla ni exigir que el usuario elija previamente el tipo de busqueda.

Texto visible sugerido: "Buscar cliente por nombre, telefono o numero de identidad". Los textos de interfaz deben respetar la regla de idioma de la seccion 73.

## 86.3 Criterios de coincidencia

| Criterio | Comportamiento requerido |
|---|---|
| Nombre | Coincidencia parcial sobre el nombre completo o sus componentes; ignorar diferencias de mayusculas y tildes durante la comparacion. |
| Telefono | Coincidencia parcial o exacta sobre una representacion comparable sin espacios, guiones o parentesis. No asumir equivalencia entre numeros con y sin prefijo internacional. |
| Numero de identidad | Coincidencia parcial o exacta sobre el identificador comparable sin espacios o guiones; conservar ceros iniciales y cualquier letra permitida por el modelo de documentos. |

Conservar los valores originales registrados. La normalizacion es para consultar, no para alterar silenciosamente los datos del huesped.

Telefono e identidad se manejan como cadenas, no como numeros. No imponer un unico formato nacional ni inventar datos faltantes. Un registro sin telefono o identidad sigue pudiendo encontrarse por nombre.

Reglas de consulta:

- no ejecutar busquedas vacias;
- solicitar al menos tres caracteres utiles antes de consultar;
- ordenar primero las coincidencias exactas de identidad o telefono;
- devolver como maximo 20 coincidencias por consulta y solicitar que se refine la busqueda si hace falta;
- no considerar el telefono como identificador unico: varios clientes pueden compartirlo;
- utilizar consultas parametrizadas y tratar los caracteres especiales del termino como texto literal;
- evitar que una respuesta de una busqueda anterior sustituya los resultados de un termino mas reciente.

## 86.4 Resultados y seleccion

Cada resultado debe permitir distinguir al cliente mediante:

- nombre completo;
- telefono, cuando exista;
- numero de identidad, cuando exista y el permiso aplicable permita mostrarlo;
- advertencia de registro inactivo o en lista negra, cuando corresponda segun las reglas existentes.

No devolver notas privadas, historial financiero completo ni campos ajenos a la seleccion. Cuando un dato no exista, mostrar un texto como "Sin registrar" y no completarlo con valores inventados.

La seleccion siempre es explicita, incluso cuando solo exista una coincidencia. Nunca elegir automaticamente entre personas con el mismo nombre o telefono.

Al seleccionar:

1. conservar el `huesped_id` real;
2. cargar los datos de identificacion del cliente en el formulario;
3. conservar los otros datos ya ingresados en la operacion;
4. mostrar el cliente seleccionado y una accion para cambiarlo;
5. usar ese mismo registro al guardar, sin crear un duplicado.

Si el usuario cambia el texto de busqueda despues de seleccionar, invalidar la seleccion anterior hasta que elija nuevamente un resultado.

## 86.5 Ausencia de coincidencias

Mostrar "No se encontraron clientes" y permitir refinar la consulta.

Ofrecer crear un nuevo huesped solamente cuando el usuario tenga `huespedes.crear`, mediante el flujo de alta existente. Conservar los datos del formulario de origen y, despues del alta correcta, permitir seleccionar el nuevo registro.

No crear un huesped automaticamente por escribir una busqueda o por no encontrar resultados. Mantener las validaciones de documentos y duplicados definidas para el alta.

## 86.6 Integracion por operacion

| Operacion | Uso de la seleccion |
|---|---|
| Nueva reservacion | Asignar el huesped seleccionado y continuar con cabana, fechas, ocupacion y las validaciones existentes. |
| Nueva factura | Reutilizar el huesped seleccionado; si la factura se vincula a una reservacion, validar que el cliente corresponde a esa reservacion. |
| Pago u otro movimiento ya definido | Utilizar la busqueda para localizar al cliente y despues elegir el documento o la operacion de origen que corresponda. No omitir las relaciones obligatorias. |

La busqueda no modifica importes, saldos, precios, estados, facturas ni pagos. Un pago continua perteneciendo a la factura requerida por el modelo financiero; seleccionar un cliente no basta para registrar dinero.

## 86.7 Autorizacion y validacion

Agregar el permiso explicito `huespedes.buscar` a la matriz de permisos y a los datos iniciales. La asignacion a roles debe seguir la matriz del sistema, sin concederlo automaticamente a todos los usuarios.

La consulta debe requerir autenticacion y `huespedes.buscar`. La operacion de destino requiere ademas su permiso correspondiente, por ejemplo `reservaciones.crear`, `facturas.crear` o `pagos.crear`.

El servidor debe validar nuevamente al guardar:

- existencia del huesped seleccionado;
- ausencia de eliminacion logica;
- reglas aplicables para registros inactivos o en lista negra;
- autorizacion del usuario para la operacion;
- coherencia entre huesped, reservacion y factura cuando existan esas relaciones.

No permitir que la busqueda o un `huesped_id` enviado manualmente evadan esas reglas. No mostrar en el buscador registros eliminados logicamente.

## 86.8 Estados de interfaz

El componente debe diferenciar:

- termino insuficiente;
- consulta en curso;
- resultados disponibles;
- ausencia de resultados;
- cliente seleccionado;
- error de consulta, con opcion de reintentar.

Un error de conexion no debe mostrarse como ausencia de clientes. Permitir navegar y seleccionar resultados con teclado, ademas de raton.

## 86.9 Implementacion y criterios de aceptacion

Construir la consulta y el componente durante la etapa de Huespedes. Reutilizarlos al implementar Reservaciones, Facturacion y Pagos, sin duplicar la logica en cada modulo.

- [ ] Se encuentra un cliente por nombre parcial, ignorando mayusculas y tildes.
- [ ] Se encuentra por telefono con o sin separadores de formato.
- [ ] Se encuentra por identidad conservando los ceros iniciales.
- [ ] Los clientes que comparten nombre o telefono aparecen como opciones separadas.
- [ ] Ningun resultado se selecciona automaticamente.
- [ ] Una busqueda vacia o insuficiente no consulta ni lista todos los clientes.
- [ ] Los resultados estan limitados y los errores se distinguen de la ausencia de coincidencias.
- [ ] Una respuesta atrasada no reemplaza los resultados del termino actual.
- [ ] La seleccion conserva el mismo `huesped_id` en la operacion y no crea otro registro.
- [ ] Cambiar de cliente conserva los otros datos del formulario.
- [ ] El alta desde el formulario solo aparece con `huespedes.crear`.
- [ ] La consulta sin autenticacion o sin `huespedes.buscar` es rechazada por el servidor.
- [ ] Se rechazan identificadores inexistentes o eliminados y relaciones inconsistentes.
- [ ] Se respetan las reglas existentes para clientes inactivos o en lista negra.
- [ ] Seleccionar un cliente no permite registrar un pago sin su factura obligatoria.
- [ ] Existen pruebas de consulta, autorizacion y asociacion en los formularios de destino.
