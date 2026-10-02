<?php

namespace App\Console\Commands;

use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrearUsuario extends Command
{
    protected $signature = 'usuarios:crear';

    protected $description = 'Crea una cuenta de personal sin asignar roles ni permisos';

    public function handle(): int
    {
        $datos = [
            'nombre' => $this->ask('Nombre completo'),
            'usuario' => $this->ask('Nombre de usuario'),
            'correo' => $this->ask('Correo electrónico'),
            'contrasena' => $this->secret('Contraseña (mínimo 12 caracteres; máximo 72 bytes)', false),
            'contrasena_confirmation' => $this->secret('Confirma la contraseña', false),
        ];

        $validacion = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuarios')],
            'correo' => ['required', 'email', 'max:255', Rule::unique('usuarios')],
            'contrasena' => ['required', 'string', 'min:12', 'confirmed', function ($atributo, $valor, $fallar) {
                if (strlen($valor) > 72) {
                    $fallar('La contraseña no puede superar los 72 bytes.');
                }
            }],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute supera el tamaño permitido.',
            'email' => 'Introduce un correo electrónico válido.',
            'unique' => 'El valor de :attribute ya está registrado.',
            'regex' => 'Usa letras minúsculas sin tildes, números, punto, guion o guion bajo en el usuario.',
            'min' => 'La contraseña debe tener al menos 12 caracteres.',
            'confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $mensaje) {
                $this->error($mensaje);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($datos) {
            $usuario = Usuario::create(collect($datos)->except('contrasena_confirmation')->all());
            RegistroAuditoria::create([
                'usuario_id' => $usuario->id,
                'modulo' => 'usuarios',
                'accion' => 'cuenta_creada_por_consola',
            ]);
        });

        $this->info('Cuenta creada. No se han asignado roles ni permisos.');

        return self::SUCCESS;
    }
}
