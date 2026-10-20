<?php

namespace App\Services;

use App\Models\FuenteCalendario;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ServicioLecturaCalendario
{
    public function __construct(private ServicioUrlCalendario $urls) {}

    public function descargar(FuenteCalendario $fuente): array
    {
        if (! extension_loaded('curl')) {
            throw new RuntimeException('La lectura segura de calendarios requiere la extensión cURL.');
        }
        $destino = $this->urls->destino($fuente->url);
        $ip = str_contains($destino['ip'], ':') ? '['.$destino['ip'].']' : $destino['ip'];
        $cabeceras = ['Accept' => 'text/calendar', 'Accept-Encoding' => 'identity'];
        if ($fuente->etag) {
            $cabeceras['If-None-Match'] = $fuente->etag;
        }
        if ($fuente->ultima_modificacion) {
            $cabeceras['If-Modified-Since'] = $fuente->ultima_modificacion;
        }
        try {
            $transporte = new CurlHandler;
            $maximo = (int) config('icalendario.max_bytes');
            $respuesta = Http::withHeaders($cabeceras)->setHandler(function ($peticion, array $opciones) use ($transporte, $maximo) {
                // El límite se aplica mientras cURL escribe el cuerpo descomprimido,
                // antes de acumular una descarga completa. Mantiene los middleware
                // de Laravel (incluidos los sustitutos HTTP de las pruebas).
                $flujo = Utils::streamFor('');
                $recibidos = 0;
                $opciones['sink'] = FnStream::decorate($flujo, ['write' => function (string $trozo) use ($flujo, &$recibidos, $maximo) {
                    $recibidos += strlen($trozo);
                    if ($recibidos > $maximo) {
                        throw new RuntimeException('El calendario supera el límite de descarga.');
                    }

                    return $flujo->write($trozo);
                }]);

                return $transporte($peticion, $opciones);
            })->connectTimeout(5)->timeout(20)
                ->withoutRedirecting()->withOptions([
                    'proxy' => '', 'verify' => true,
                    'curl' => [CURLOPT_RESOLVE => [$destino['host'].':443:'.$ip]],
                ])->get($fuente->url);
            if ($respuesta->status() === 304) {
                return ['sin_cambios' => true];
            }
            if ($respuesta->status() !== 200) {
                throw new RuntimeException;
            }
            $cuerpo = $respuesta->toPsrResponse()->getBody();
            $contenido = '';
            while (! $cuerpo->eof()) {
                $contenido .= $cuerpo->read(8192);
                if (strlen($contenido) > config('icalendario.max_bytes')) {
                    throw new RuntimeException;
                }
            }

            return ['sin_cambios' => false, 'contenido' => $contenido,
                'etag' => mb_substr($respuesta->header('ETag'), 0, 1000),
                'ultima_modificacion' => mb_substr($respuesta->header('Last-Modified'), 0, 255)];
        } catch (Throwable) {
            // Ninguna excepción conserva la URL privada en su mensaje.
            throw new RuntimeException('No se pudo leer el calendario completo dentro de los límites permitidos.');
        }
    }
}
