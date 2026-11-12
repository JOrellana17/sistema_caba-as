<?php

// Proveedor que integra permisos y convenciones de interfaz con el arranque de Laravel.

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Foundation\DevCommands;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // Punto de registro de servicios del contenedor; actualmente no agrega enlaces personalizados.
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    // Configura el programador local, la paginación Bootstrap y un Gate por cada permiso explícito del
    // catálogo; la decisión final se delega al usuario.
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local')) {
            DevCommands::artisan('schedule:work', 'programador');
        }
        Paginator::useBootstrapFive();
        foreach (config('permisos') as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $codigo = $modulo.'.'.$accion;
                Gate::define($codigo, fn (Usuario $usuario) => $usuario->tienePermiso($codigo));
            }
        }
    }
}
