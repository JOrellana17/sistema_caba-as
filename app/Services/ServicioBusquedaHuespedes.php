<?php

// Servicio de busqueda huespedes. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Huesped;
use App\Support\NormalizadorHuesped;
use Illuminate\Database\Eloquent\Builder;

class ServicioBusquedaHuespedes
{
    // Añade coincidencias parciales por nombre, teléfono o identidad normalizados; exige tres
    // caracteres útiles y prioriza coincidencias exactas de teléfono o identidad.
    public function filtrar(Builder $consulta, string $termino): Builder
    {
        $nombre = NormalizadorHuesped::nombre($termino);
        $telefono = NormalizadorHuesped::telefono($termino);
        $identidad = NormalizadorHuesped::identidad($termino);

        if (NormalizadorHuesped::caracteresUtiles($termino) < 3) {
            return $consulta->whereRaw('1 = 0');
        }

        return $consulta->where(function (Builder $consulta) use ($nombre, $telefono, $identidad) {
            $consulta->whereRaw("nombre_busqueda LIKE ? ESCAPE '!'", [NormalizadorHuesped::patron($nombre)])
                ->orWhereRaw("telefono_busqueda LIKE ? ESCAPE '!'", [NormalizadorHuesped::patron($telefono)])
                ->orWhereRaw("identidad_busqueda LIKE ? ESCAPE '!'", [NormalizadorHuesped::patron($identidad)]);
        })->orderByRaw('CASE WHEN identidad_busqueda = ? OR telefono_busqueda = ? THEN 0 ELSE 1 END', [$identidad, $telefono])
            ->orderBy('nombre_busqueda')->orderBy('id');
    }

    // Ejecuta la consulta limitada a 21 filas para devolver como máximo 20 y señalar si hay más.
    // Selecciona la identidad únicamente cuando está autorizada.
    public function buscar(string $termino, bool $mostrarIdentidad): array
    {
        if (NormalizadorHuesped::caracteresUtiles($termino) < 3) {
            return ['resultados' => [], 'hay_mas' => false];
        }

        $columnas = ['id', 'nombre', 'telefono', 'activo', 'lista_negra'];
        if ($mostrarIdentidad) {
            $columnas[] = 'numero_identidad';
        }
        $huespedes = $this->filtrar(Huesped::query(), $termino)->limit(21)->get($columnas);

        return [
            'resultados' => $huespedes->take(20)->map(fn (Huesped $huesped) => $this->resumir($huesped, $mostrarIdentidad))->values()->all(),
            'hay_mas' => $huespedes->count() > 20,
        ];
    }

    // Construye los datos públicos de una coincidencia y sus advertencias; excluye notas privadas y
    // oculta la identidad cuando corresponde.
    public function resumir(Huesped $huesped, bool $mostrarIdentidad): array
    {
        return [
            'id' => $huesped->id,
            'nombre' => $huesped->nombre,
            'telefono' => $huesped->telefono,
            'numero_identidad' => $mostrarIdentidad ? $huesped->numero_identidad : null,
            'identidad_visible' => $mostrarIdentidad,
            'activo' => $huesped->activo,
            'lista_negra' => $huesped->lista_negra,
        ];
    }
}
