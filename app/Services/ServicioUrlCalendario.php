<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ServicioUrlCalendario
{
    public function validar(string $url): array
    {
        $partes = parse_url($url);
        $host = strtolower($partes['host'] ?? '');
        $permitido = collect(config('icalendario.hosts_permitidos'))->contains(fn ($dominio) => $host === $dominio || str_ends_with($host, '.'.$dominio));
        if (! $partes || ($partes['scheme'] ?? '') !== 'https' || ! $permitido
            || isset($partes['user']) || isset($partes['pass']) || isset($partes['fragment'])
            || (isset($partes['port']) && $partes['port'] !== 443) || str_contains($url, "\r") || str_contains($url, "\n")) {
            throw ValidationException::withMessages(['url' => 'Usa una URL HTTPS de Airbnb o Booking sin credenciales ni puertos alternativos.']);
        }

        return ['host' => $host];
    }

    public function destino(string $url): array
    {
        $host = $this->validar($url)['host'];
        $direcciones = $this->resolver($host);
        if (! $direcciones || collect($direcciones)->contains(fn ($ip) => ! $this->publica($ip))) {
            throw ValidationException::withMessages(['url' => 'El calendario no resuelve exclusivamente a direcciones públicas permitidas.']);
        }

        return ['host' => $host, 'ip' => $direcciones[0]];
    }

    public function publica(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (str_contains($ip, ':')) {
            // Solo unicast global; rechaza mapped IPv4, multicast y traducciones.
            $binaria = inet_pton($ip);
            $hexadecimal = bin2hex($binaria);

            return (ord($binaria[0]) & 0xE0) === 0x20
                && ! str_starts_with($hexadecimal, '2002')
                && ! str_starts_with($hexadecimal, '20010000')
                && ! str_starts_with($hexadecimal, '20010db8')
                && ! str_starts_with($hexadecimal, '2001001')
                && ! str_starts_with($hexadecimal, '2001002')
                && ! str_starts_with($hexadecimal, '3fff0');
        }
        $octetos = array_map('intval', explode('.', $ip));

        return ! ($octetos[0] === 0 || $octetos[0] >= 224
            || ($octetos[0] === 100 && $octetos[1] >= 64 && $octetos[1] <= 127)
            || ($octetos[0] === 192 && $octetos[1] === 0)
            || ($octetos[0] === 198 && in_array($octetos[1], [18, 19], true)));
    }

    protected function resolver(string $host): array
    {
        $registros = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(fn ($registro) => $registro['ip'] ?? $registro['ipv6'] ?? null, $registros ?: []))));
    }
}
