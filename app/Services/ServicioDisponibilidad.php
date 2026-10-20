<?php

namespace App\Services;

use App\Models\Cabana;
use App\Models\EventoCalendario;
use App\Models\Reservacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServicioDisponibilidad
{
    public const ZONA_HORARIA = 'America/Tegucigalpa';

    /** @return array{entrada: CarbonImmutable, salida: CarbonImmutable} */
    public function periodo(string $fechaEntrada, string $fechaSalida, string $horaEntrada = '14:00', string $horaSalida = '11:00'): array
    {
        Validator::make(
            ['fecha_entrada' => $fechaEntrada, 'fecha_salida' => $fechaSalida, 'hora_entrada' => $horaEntrada, 'hora_salida' => $horaSalida],
            ['fecha_entrada' => ['required', 'date_format:Y-m-d'], 'fecha_salida' => ['required', 'date_format:Y-m-d', 'after:fecha_entrada'], 'hora_entrada' => ['required', 'date_format:H:i'], 'hora_salida' => ['required', 'date_format:H:i']],
            ['date_format' => 'Introduce una fecha válida.', 'after' => 'La salida debe ser posterior a la entrada.'],
        )->validate();

        return [
            'entrada' => CarbonImmutable::createFromFormat('!Y-m-d', $fechaEntrada, self::ZONA_HORARIA)->setTimeFromTimeString($horaEntrada)->utc(),
            'salida' => CarbonImmutable::createFromFormat('!Y-m-d', $fechaSalida, self::ZONA_HORARIA)->setTimeFromTimeString($horaSalida)->utc(),
        ];
    }

    /** Las exclusiones solo proceden de un caso de uso autorizado, nunca de una consulta pública. */
    public function disponibles(string $fechaEntrada, string $fechaSalida, int $cantidadHuespedes, ?Reservacion $excluida = null, string $horaEntrada = '14:00', string $horaSalida = '11:00', ?EventoCalendario $eventoExcluido = null): Builder
    {
        if ($cantidadHuespedes < 1 || $cantidadHuespedes > 65535) {
            throw ValidationException::withMessages(['cantidad_huespedes' => 'Introduce una cantidad de huéspedes válida.']);
        }
        $periodo = $this->periodo($fechaEntrada, $fechaSalida, $horaEntrada, $horaSalida);

        return Cabana::query()->where('activa', true)->where('estado', 'disponible')
            ->where('capacidad_maxima', '>=', $cantidadHuespedes)
            ->whereDoesntHave('reservaciones', function (Builder $consulta) use ($periodo, $excluida) {
                $consulta->whereIn('estado', Reservacion::ESTADOS_QUE_BLOQUEAN)
                    ->where('entrada_prevista', '<', $periodo['salida'])->where('salida_prevista', '>', $periodo['entrada']);
                if ($excluida?->exists) {
                    $consulta->whereKeyNot($excluida->getKey());
                }
            })->whereDoesntHave('eventosCalendario', function (Builder $consulta) use ($periodo, $excluida, $eventoExcluido) {
                $consulta->where('bloquea', true)->where('inicio', '<', $periodo['salida'])->where('fin', '>', $periodo['entrada']);
                if ($excluida?->exists) {
                    $consulta->where(fn ($restantes) => $restantes->whereNull('reservacion_id')->orWhere('reservacion_id', '!=', $excluida->id));
                }
                if ($eventoExcluido?->exists) {
                    $consulta->whereKeyNot($eventoExcluido->id);
                }
            });
    }

    /** Debe repetirse bajo bloqueo de cabaña durante una escritura. */
    public function verificar(Cabana $cabana, string $fechaEntrada, string $fechaSalida, int $cantidadHuespedes, ?Reservacion $excluida = null, string $horaEntrada = '14:00', string $horaSalida = '11:00', bool $entradaReal = false, ?EventoCalendario $eventoExcluido = null): bool
    {
        $consulta = $this->disponibles($fechaEntrada, $fechaSalida, $cantidadHuespedes, $excluida, $horaEntrada, $horaSalida, $eventoExcluido)->whereKey($cabana->getKey());
        if ($entradaReal) {
            $consulta->whereDoesntHave('reservaciones', function (Builder $reservaciones) use ($excluida) {
                $reservaciones->where('estado', 'hospedada');
                if ($excluida?->exists) {
                    $reservaciones->whereKeyNot($excluida->getKey());
                }
            });
        }

        return $consulta->exists();
    }
}
