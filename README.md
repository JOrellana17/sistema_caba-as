# Cabañas El Capitán

Sistema administrativo en Laravel + Blade + Bootstrap 5 + Vite.

## Requisitos

- PHP 8.3+
- Composer
- Node.js 20+ y npm
- MySQL 8+
- Extensiones de PHP necesarias para Laravel, incluida `pdo_mysql`
- Extensión PHP `zip` habilitada para que Composer pueda descargar dependencias

## Instalación en máquina host

1. Crea en MySQL la base de datos y el usuario que usará la aplicación. Por defecto, el nombre esperado es `sistema_cabanas`.

2. Crea `.env` a partir del ejemplo. En PowerShell:

```powershell
Copy-Item .env.example .env
```

Si ya existe un `.env`, no lo sobrescribas. Edita el archivo y configura al menos:

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

3. Instala las dependencias de PHP y JavaScript:

```powershell
composer install
npm.cmd ci --ignore-scripts
```

Si Composer informa que no puede extraer los paquetes descargados, habilita la extensión `zip` en el PHP de línea de comandos que estás usando para Composer.

4. Genera la clave de la aplicación solo en una instalación nueva cuyo `APP_KEY` esté vacío. No ejecutes este comando si estás reutilizando un `.env` existente:

```powershell
php artisan key:generate
```

5. Limpia la configuración, ejecuta las migraciones y limpia la caché:

```powershell
php artisan config:clear
php artisan migrate
php artisan cache:clear
```

`cache:clear` se ejecuta después de las migraciones porque la configuración predeterminada almacena la caché en la base de datos.

6. Compila los assets del frontend:

```powershell
npm.cmd run build
```

Para comprobar el estado de las migraciones:

```powershell
php artisan migrate:status
```

La migración inicial crea la cuenta `admin` con contraseña `admin123`. Cambia esa contraseña inmediatamente después del primer inicio de sesión y no uses esas credenciales en producción.

## Desarrollo

Para recompilar los assets automáticamente durante el desarrollo:

```bash
npm.cmd run dev
```

En otra terminal, inicia la aplicación:

```bash
php artisan serve
```

Abre la URL que muestra Artisan (por defecto, `http://127.0.0.1:8000`).
