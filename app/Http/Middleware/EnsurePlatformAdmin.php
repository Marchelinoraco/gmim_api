<?php

namespace App\Http\Middleware;

use App\Models\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof PlatformAdmin || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin platform yang diizinkan.',
            ], 403);
        }

        return $next($request);
    }
}
