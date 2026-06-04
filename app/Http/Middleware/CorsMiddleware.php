<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin        = $request->headers->get('Origin', '');
        $allowedOrigin = $this->isAllowed($origin) ? $origin : '';

        // Preflight
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204, $this->headers($allowedOrigin));
        }

        $response = $next($request);

        foreach ($this->headers($allowedOrigin) as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * Origin diizinkan jika:
     *  1. Cocok persis dengan salah satu CORS_ALLOWED_ORIGINS (mis. http://localhost:5173), ATAU
     *  2. Host-nya == CORS_ALLOWED_DOMAIN atau berakhiran ".{CORS_ALLOWED_DOMAIN}"
     *     (mendukung multi-tenant: gmim-bethesda.domain.com, gmim-x.domain.com, dst).
     */
    private function isAllowed(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        // 1. Exact match (dev / origin spesifik)
        if (in_array($origin, $this->exactOrigins(), true)) {
            return true;
        }

        // 2. Wildcard base domain
        $domain = trim((string) env('CORS_ALLOWED_DOMAIN', ''));
        if ($domain === '') {
            return false;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    private function exactOrigins(): array
    {
        return array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
        ));
    }

    private function headers(string $allowedOrigin): array
    {
        $headers = [
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With, Accept',
            'Access-Control-Allow-Credentials' => 'true',
            // Penting saat origin dinamis: respons bisa berbeda per Origin
            'Vary'                             => 'Origin',
        ];

        // Hanya kirim ACAO bila origin diizinkan — kalau tidak, browser memblokir (aman).
        if ($allowedOrigin !== '') {
            $headers['Access-Control-Allow-Origin'] = $allowedOrigin;
        }

        return $headers;
    }
}
