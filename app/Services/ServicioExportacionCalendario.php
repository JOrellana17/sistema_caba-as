<?php

namespace App\Services;

use App\Models\Cabana;
use App\Models\EnlaceCalendario;
use App\Models\EventoCalendario;
use App\Models\Reservacion;
use Carbon\CarbonImmutable;
use Sabre\VObject\Component\VCalendar;

class ServicioExportacionCalendario
{
    public function generar(?int $cabanaId, CarbonImmutable $desde, CarbonImmutable $hasta, ?Reservacion $individual = null): string
    {
        $inicio = CarbonImmutable::parse($desde->format('Y-m-d'), ServicioDisponibilidad::ZONA_HORARIA)->utc();
        $fin = CarbonImmutable::parse($hasta->format('Y-m-d'), ServicioDisponibilidad::ZONA_HORARIA)->addDay()->utc();
        abort_if($fin->lessThanOrEqualTo($inicio), 422, 'Selecciona un rango válido.');
        if (! $individual) {
            // Un estado global sin periodo no se publica como disponibilidad completa.
            $incompleta = Cabana::query()->when($cabanaId, fn ($consulta) => $consulta->whereKey($cabanaId))
                ->where(fn ($consulta) => $consulta->where('estado', '!=', 'disponible')->orWhere('activa', false))
                ->whereDoesntHave('eventosCalendario', fn ($consulta) => $consulta->where('origen', 'cec')->where('bloquea', true)
                    ->where('fin', '>', now()))->exists();
            abort_if($incompleta, 503, 'Registra un período para los estados manuales antes de exportar.');
        }
        $calendario = new VCalendar(['PRODID' => '-//CEC//Calendario//ES', 'X-WR-CALNAME' => 'Disponibilidad CEC']);
        $reservas = Reservacion::whereIn('estado', Reservacion::ESTADOS_QUE_BLOQUEAN)
            ->when($cabanaId, fn ($consulta) => $consulta->where('cabana_id', $cabanaId))
            ->when($individual, fn ($consulta) => $consulta->whereKey($individual->id))
            ->where('entrada_prevista', '<', $fin)->where('salida_prevista', '>', $inicio)
            // No reenviar estancias importadas a canales hasta validar ecos reales.
            ->whereDoesntHave('eventoCalendario', fn ($consulta) => $consulta->where('origen', '!=', 'cec'))->orderBy('id')->get();
        foreach ($reservas as $registro) {
            $calendario->add('VEVENT', ['UID' => 'cec-reserva-'.$registro->codigo.'@calendario.cec',
                'DTSTAMP' => $registro->updated_at->toDateTimeImmutable(), 'DTSTART' => $registro->entrada_prevista->toDateTimeImmutable(),
                'DTEND' => $registro->salida_prevista->toDateTimeImmutable(), 'SUMMARY' => 'No disponible']);
        }
        if (! $individual) {
            foreach (EventoCalendario::where('origen', 'cec')->where('bloquea', true)
                ->when($cabanaId, fn ($consulta) => $consulta->where('cabana_id', $cabanaId))
                ->where('inicio', '<', $fin)->where('fin', '>', $inicio)->orderBy('id')->get() as $evento) {
                $calendario->add('VEVENT', ['UID' => $evento->uid, 'DTSTAMP' => $evento->updated_at->toDateTimeImmutable(),
                    'DTSTART' => $evento->inicio->toDateTimeImmutable(), 'DTEND' => $evento->fin->toDateTimeImmutable(), 'SUMMARY' => 'No disponible']);
            }
        }

        return $calendario->serialize();
    }

    public function feed(EnlaceCalendario $enlace): string
    {
        abort_if($enlace->hasta->format('Y-m-d') < now(ServicioDisponibilidad::ZONA_HORARIA)->format('Y-m-d'), 410, 'El rango del enlace expiró.');

        return $this->generar($enlace->cabana_id, $enlace->desde, $enlace->hasta);
    }
}
