<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToGereja
{
    /**
     * Pastikan user yang sedang login hanya bisa mengakses data gerejanya sendiri.
     * Route parameter {gereja} harus cocok dengan gereja_id user yang terautentikasi.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $gerejaParam = $request->route('gereja');

        if (! $user || $user->gereja_id !== $gerejaParam) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki akses ke gereja ini.',
            ], 403);
        }

        return $next($request);
    }
}
