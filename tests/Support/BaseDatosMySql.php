<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

final class BaseDatosMySql
{
    public const PREFIJO = 'sistema_cabanas_pruebas';

    private static ?self $principal = null;

    /** @var array<string, self> */
    private static array $creadas = [];

    private static bool $limpiezaRegistrada = false;

    public bool $migrada = false;

    private function __construct(
        public readonly string $nombre,
        private readonly PDO $servidor,
        private readonly array $configuracion,
    ) {}

    public static function principal(array $configuracion): self
    {
        return self::$principal ??= self::crear($configuracion);
    }

    public static function crear(array $configuracion): self
    {
        if (! app()->environment('testing') || ($configuracion['driver'] ?? null) !== 'mysql'
            || ! empty($configuracion['url'])
            || (($configuracion['database'] ?? null) !== self::PREFIJO && ! isset(self::$creadas[$configuracion['database'] ?? '']))) {
            throw new RuntimeException('Las pruebas requieren MySQL, entorno testing y un destino de pruebas propio.');
        }

        $nombre = self::PREFIJO.'_'.bin2hex(random_bytes(8));
        $direccion = empty($configuracion['unix_socket'])
            ? 'host='.$configuracion['host'].';port='.$configuracion['port']
            : 'unix_socket='.$configuracion['unix_socket'];

        try {
            $servidor = new PDO('mysql:'.$direccion.';charset=utf8mb4', $configuracion['username'], $configuracion['password'], array_replace($configuracion['options'] ?? [], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]));
            $consulta = $servidor->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $consulta->execute([$nombre]);
            if ((int) $consulta->fetchColumn() !== 0) {
                throw new RuntimeException('El esquema generado ya existe.');
            }
            // CREATE sin IF NOT EXISTS impide apropiarse de una base ajena.
            $servidor->exec('CREATE DATABASE `'.$nombre.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable) {
            throw new RuntimeException('No se pudo crear una base MySQL nueva de pruebas. Revisa el acceso y los permisos de esquemas de pruebas.');
        }

        $base = new self($nombre, $servidor, array_replace($configuracion, ['database' => $nombre, 'url' => null]));
        self::$creadas[$nombre] = $base;
        if (! self::$limpiezaRegistrada) {
            self::$limpiezaRegistrada = true;
            register_shutdown_function(function () {
                foreach (self::$creadas as $baseCreada) {
                    try {
                        $baseCreada->eliminar();
                    } catch (Throwable) {
                        fwrite(STDERR, 'No se pudo retirar un esquema temporal de pruebas: '.$baseCreada->nombre."\n");
                    }
                }
            });
        }

        return $base;
    }

    public function conectar(string $conexion = 'mysql'): void
    {
        if (! isset(self::$creadas[$this->nombre]) || ! app()->environment('testing')) {
            throw new RuntimeException('La base no pertenece a esta ejecución de pruebas.');
        }
        DB::purge($conexion);
        config(['database.connections.'.$conexion => $this->configuracion]);
    }

    public function eliminar(): void
    {
        if (! isset(self::$creadas[$this->nombre])) {
            return;
        }
        // Nombre inmutable generado aquí; nunca se elimina un esquema preexistente.
        $this->servidor->exec('DROP DATABASE `'.$this->nombre.'`');
        unset(self::$creadas[$this->nombre]);
    }
}
