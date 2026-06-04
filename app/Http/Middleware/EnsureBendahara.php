<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBendahara
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'Bendahara') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Bendahara yang dapat melakukan tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
