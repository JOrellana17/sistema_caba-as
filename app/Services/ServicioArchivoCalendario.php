<?php

// Servicio de archivo calendario. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use RuntimeException;
use Sabre\VObject\Reader;
use Throwable;

class ServicioArchivoCalendario
{
    // Convierte un ICS completo en eventos normalizados a UTC. Rechaza estructuras, fechas, UID
    // duplicados, recurrencias y adjuntos no admitidos antes de que puedan modificar disponibilidad.
    public function analizar(string $contenido, string $interpretacion, string $zona): array
    {
        if (strlen($contenido) > config('icalendario.max_bytes') || ! in_array($interpretacion, ['noches_estancia', 'dias_completos'], true)) {
            throw new RuntimeException('Archivo o interpretación no permitidos.');
        }
        // Comprueba límites y equilibrio BEGIN/END antes de entregar contenido al analizador ICS.
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $pila = [];
        foreach ($lineas as $linea) {
            if (strlen($linea) > 8192) {
                throw new RuntimeException('El archivo contiene líneas demasiado largas.');
            }
            if (str_starts_with($linea, 'BEGIN:')) {
                $pila[] = substr($linea, 6);
                if (count($pila) > 5) {
                    throw new RuntimeException('La estructura del calendario excede los límites.');
                }
            } elseif (str_starts_with($linea, 'END:') && array_pop($pila) !== substr($linea, 4)) {
                throw new RuntimeException('El calendario está truncado o tiene una estructura inválida.');
            }
        }
        if ($pila || ($lineas[0] ?? '') !== 'BEGIN:VCALENDAR' || end($lineas) !== 'END:VCALENDAR'
            || substr_count($contenido, 'BEGIN:VCALENDAR') !== 1) {
            throw new RuntimeException('Se requiere un calendario ICS completo.');
        }
        try {
            // Exige el formato admitido y limita los componentes y la cantidad de eventos.
            $calendario = Reader::read($contenido);
            if ((string) $calendario->VERSION !== '2.0' || ! isset($calendario->PRODID)) {
                throw new RuntimeException;
            }
            foreach ($calendario->getComponents() as $componente) {
                if (! in_array($componente->name, ['VEVENT', 'VTIMEZONE'], true)) {
                    throw new RuntimeException;
                }
            }
            $eventos = $calendario->select('VEVENT');
            if (count($eventos) > config('icalendario.max_eventos')) {
                throw new RuntimeException;
            }
            $resultado = [];
            foreach ($eventos as $evento) {
                // Evita propiedades ambiguas o repetidas que puedan producir interpretaciones distintas del período.
                foreach (['UID', 'DTSTART', 'DTEND', 'DURATION', 'STATUS'] as $unica) {
                    if (count($evento->select($unica)) > 1) {
                        throw new RuntimeException;
                    }
                }
                if (isset($evento->DTEND, $evento->DURATION)) {
                    throw new RuntimeException;
                }
                // Usa la huella del UID como clave para detectar duplicados sin truncar el identificador externo.
                $uid = (string) $evento->UID;
                if ($uid === '' || strlen($uid) > 1024 || isset($resultado[hash('sha256', $uid)])) {
                    throw new RuntimeException;
                }
                foreach (['RRULE', 'RDATE', 'EXDATE', 'RECURRENCE-ID', 'ATTACH'] as $propiedad) {
                    if (isset($evento->{$propiedad})) {
                        throw new RuntimeException('Recurrencias, excepciones o adjuntos requieren revisión; no se ha liberado disponibilidad.');
                    }
                }
                // Conserva una señal de cancelación para que la sincronización aplique sus reglas de liberación.
                if ((string) $evento->STATUS === 'CANCELLED') {
                    $resultado[hash('sha256', $uid)] = ['uid' => $uid, 'cancelada' => true];

                    continue;
                }
                if (! isset($evento->DTSTART)) {
                    throw new RuntimeException;
                }
                // Valida zona, formato y fecha real antes de convertirlos; no acepta normalizaciones silenciosas.
                foreach (['DTSTART', 'DTEND'] as $campoFecha) {
                    if (isset($evento->{$campoFecha}['TZID'])) {
                        new DateTimeZone((string) $evento->{$campoFecha}['TZID']);
                    }
                    if (isset($evento->{$campoFecha})) {
                        $originalFecha = (string) $evento->{$campoFecha};
                        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})Z?)?$/D', $originalFecha, $partesFecha)
                            || ! checkdate((int) $partesFecha[2], (int) $partesFecha[3], (int) $partesFecha[1])
                            || (isset($partesFecha[4]) && ((int) $partesFecha[4] > 23 || (int) $partesFecha[5] > 59 || (int) $partesFecha[6] > 59))) {
                            throw new RuntimeException;
                        }
                    }
                }
                // Distingue fechas sin hora y eventos con hora; calcula el final según DTEND, duración o día completo.
                $completa = ! $evento->DTSTART->hasTime();
                $inicio = CarbonImmutable::instance($evento->DTSTART->getDateTime(new DateTimeZone($zona)));
                if (isset($evento->DTEND)) {
                    if ($evento->DTEND->hasTime() === $completa) {
                        throw new RuntimeException;
                    }
                    $fin = CarbonImmutable::instance($evento->DTEND->getDateTime(new DateTimeZone($zona)));
                } elseif (isset($evento->DURATION)) {
                    $fin = $inicio->add($evento->DURATION->getDateInterval());
                } elseif ($completa) {
                    $fin = $inicio->addDay();
                } else {
                    throw new RuntimeException;
                }
                // Interpreta las fechas de estancia con entrada a las 14:00 y salida a las 11:00 en la zona del negocio.
                if ($completa && $interpretacion === 'noches_estancia') {
                    $inicio = CarbonImmutable::parse($inicio->format('Y-m-d').' 14:00', ServicioDisponibilidad::ZONA_HORARIA);
                    $fin = CarbonImmutable::parse($fin->format('Y-m-d').' 11:00', ServicioDisponibilidad::ZONA_HORARIA);
                }
                if ($fin->lessThanOrEqualTo($inicio)) {
                    throw new RuntimeException;
                }
                // Entrega intervalos UTC para compararlos con las demás fuentes de disponibilidad.
                $resultado[hash('sha256', $uid)] = ['uid' => $uid, 'inicio' => $inicio->utc(), 'fin' => $fin->utc(), 'fecha_completa' => $completa, 'cancelada' => false];
            }

            return $resultado;
        } catch (Throwable $error) {
            throw new RuntimeException('Calendario inválido, duplicado o con eventos no admitidos. Revisa fechas, zonas, recurrencias y adjuntos.');
        }
    }
}
