# Cabañas El Capitán

Sistema administrativo en Laravel + Blade + Bootstrap 5 + Vite.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 20+ y npm
- MySQL 8+

## Configurar entorno (`.env`)

1. Copia el archivo de ejemplo:

```bash
copy .env.example .env
```

2. Edita `.env` y define al menos:

```dotenv
APP_NAME="Cabanas El Capitan"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_cabanas
DB_USERNAME=tu_usuario_mysql
DB_PASSWORD=tu_password_mysql
```

3. Genera la clave de la aplicación:

```bash
php artisan key:generate
```

## Instalación en máquina host

```bash
composer install
npm.cmd ci --ignore-scripts
php artisan config:clear
php artisan cache:clear
```

## Migraciones

Ejecuta migraciones sobre la base configurada en `.env`:

```bash
php artisan migrate --force
```

Verificar estado:

```bash
php artisan migrate:status
```

## Build de frontend

Compilar assets para producción:

```bash
npm.cmd run build
```

Modo desarrollo (watch):

```bash
npm.cmd run dev
```

## Ejecutar aplicación

```bash
php artisan serve
```
