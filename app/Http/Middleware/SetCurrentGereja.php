<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bind gereja_id aktif ke app container agar Global Scope BelongsToGereja bisa bekerja.
 * Harus dijalankan di dalam route group prefix('gereja/{gereja}').
 */
class SetCurrentGereja
{
    public function handle(Request $request, Closure $next): Response
    {
        $gerejaId = $request->route('gereja');
        if ($gerejaId) {
            app()->instance('currentGerejaId', $gerejaId);
        }

        return $next($request);
    }
}
