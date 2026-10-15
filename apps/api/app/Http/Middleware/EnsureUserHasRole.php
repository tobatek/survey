<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Pemeriksaan role dasar (FSD §5.3 tabel otorisasi). Pemeriksaan resource-
// level yang lebih halus (mis. scope prodi/unit operator) dilakukan lewat
// Laravel Policy per domain mulai Fase 2 — middleware ini hanya gerbang
// role sederhana untuk endpoint lintas-domain seperti trigger sync.
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->loadMissing('roles')->hasRole(...$roles)) {
            abort(403, 'Anda tidak memiliki hak akses untuk aksi ini.');
        }

        return $next($request);
    }
}
