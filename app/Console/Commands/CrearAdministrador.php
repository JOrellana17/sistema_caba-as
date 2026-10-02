<?php

namespace App\Console\Commands;

use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CrearAdministrador extends Command
{
    protected $signature = 'usuarios:crear-administrador {usuario} {--longitud-minima=12 : Longitud de la clave inicial autorizada por el operador}';

    protected $description = 'Crea una cuenta administradora mediante contraseña oculta';

    public function handle(): int
    {
        $minimo = filter_var($this->option('longitud-minima'), FILTER_VALIDATE_INT);
        if ($minimo === false || $minimo < 8 || $minimo > 72) {
            $this->error('La longitud mínima debe estar entre 8 y 72.');

            return self::FAILURE;
        }
        $datos = [
            'usuario' => $this->argument('usuario'),
            'contrasena' => $this->secret('Contraseña inicial', false),
            'contrasena_confirmation' => $this->secret('Confirma la contraseña', false),
        ];
        $validacion = Validator::make($datos, [
            'usuario' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/', 'unique:usuarios,usuario'],
            'contrasena' => ['required', 'string', 'min:'.$minimo, 'confirmed', function ($atributo, $valor, $fallar) {
                if (strlen($valor) > 72) {
                    $fallar('La contraseña supera el límite de 72 bytes.');
                }
            }],
        ], [
            'unique' => 'La cuenta ya existe; no se reemplazó.',
            'required' => 'Completa todos los datos.',
            'string' => 'El dato debe ser texto.',
            'regex' => 'El nombre de usuario no es válido.',
            'max' => 'El dato supera el tamaño permitido.',
            'min' => 'La contraseña no cumple la longitud mínima indicada.',
            'confirmed' => 'Las contraseñas no coinciden.',
        ]);
        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $mensaje) {
                $this->error($mensaje);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($datos) {
            (new PermisosSeeder)->run();
            $usuario = Usuario::create([
                'nombre' => 'Administrador',
                'usuario' => $datos['usuario'],
                'correo' => null,
                'contrasena' => $datos['contrasena'],
            ]);
            $usuario->roles()->attach(Rol::where('codigo', 'administrador')->sole());
            RegistroAuditoria::create([
                'modulo' => 'usuarios',
                'accion' => 'administrador_creado_por_consola',
                'registro_id' => $usuario->id,
            ]);
        });
        $this->info('Cuenta administradora creada correctamente.');

        return self::SUCCESS;
    }
}
