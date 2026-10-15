<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Fase 6 paket B (FSD §21.1 "content security policy dan secure headers")
// -- sebelum ini TIDAK ADA header keamanan sama sekali. Backend ini API-only
// (selalu JSON, tidak pernah merender HTML aplikasi sendiri), jadi CSP boleh
// seketat `default-src 'none'` -- tidak ada halaman di sini yang butuh
// memuat skrip/gambar/font apa pun. `Strict-Transport-Security` HANYA
// dikirim saat production supaya tidak memaksa HTTPS ke dev lokal (HTTP).
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
