<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        foreach (config('permisos') as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $codigo = $modulo.'.'.$accion;
                Gate::define($codigo, fn (Usuario $usuario) => $usuario->tienePermiso($codigo));
            }
        }
    }
}
