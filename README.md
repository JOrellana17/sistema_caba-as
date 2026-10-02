# Cabañas El Capitán

Sistema administrativo interno construido en esta aplicación Laravel, con Blade, Bootstrap 5 y Vite. Utiliza **MySQL** en desarrollo y pruebas; no hay registro ni reservaciones públicas.

## Instalación y desarrollo

Consultar [docs/INSTALACION.md](docs/INSTALACION.md). `.env.example` utiliza MySQL y `sistema_cabanas`; las credenciales se completan en el entorno privado. Conservar la clave y los datos existentes. Verificar el destino antes de aplicar migraciones.

```text
composer install
npm.cmd ci --ignore-scripts
php artisan config:clear
php artisan env
php artisan migrate:status
npm.cmd run build
php artisan serve
```

Una instalación nueva necesita crear su base MySQL y configurar acceso antes de migrar. Esta instalación ya tiene sus datos y usuario administrativo; no reiniciar ni repetir su importación.

## Pruebas

```text
php artisan test
php vendor/bin/pint --test
node --test tests/js/buscador-huesped.test.js
npm.cmd run build
php artisan view:cache
```

PHPUnit fuerza `testing` y MySQL. El soporte crea esquemas con nombres aleatorios `sistema_cabanas_pruebas_*`, revierte transacciones entre pruebas y retira únicamente los esquemas creados por la ejecución. Requiere acceso para crear/retirar esos esquemas; nunca utiliza ni vacía `sistema_cabanas`. No se usa `migrate:fresh` ni `db:wipe`. Las credenciales se cargan de `.env` o de un `.env.testing` privado y excluido de Git.

## Documentos

- [AGENTS.md](AGENTS.md): instrucciones de trabajo.
- [INSTRUCCIONES_AGENTE_CONSTRUCTOR_CEC.md](INSTRUCCIONES_AGENTE_CONSTRUCTOR_CEC.md): orden de construcción.
- [SYSTEM_SPEC_CEC_RECONSTRUCCION.md](SYSTEM_SPEC_CEC_RECONSTRUCCION.md): requisitos; prevalecen las secciones 72 en adelante y las decisiones posteriores del usuario.
- [docs/PLAN_IMPLEMENTACION.md](docs/PLAN_IMPLEMENTACION.md): fases y comprobaciones reales.
- [docs/DECISIONES.md](docs/DECISIONES.md): decisiones vigentes e historial.
- [docs/PLAN_CAMBIO_MYSQL.md](docs/PLAN_CAMBIO_MYSQL.md): cambio de configuración y pruebas a MySQL.

Las migraciones y resultados antiguos se conservan como evidencia. El archivo SQLite anterior permanece excluido de Git como origen histórico, sin conexión activa.
