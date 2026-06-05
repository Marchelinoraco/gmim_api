<?php

namespace App\Http\Middleware;

use App\Models\Gereja;
use App\Models\Langganan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $gerejaId = app()->bound('currentGerejaId') ? app('currentGerejaId') : null;

        if (! $gerejaId) {
            return $next($request);
        }

        $langganan = Langganan::where('gereja_id', $gerejaId)->first();

        // Gereja tanpa record langganan dibiarkan lanjut (safety — jangan blokir)
        if (! $langganan) {
            return $next($request);
        }

        $status = $langganan->statusEfektif();

        // Sinkronkan cache status di tabel gereja jika berbeda
        if ($langganan->status !== $status) {
            $langganan->update(['status' => $status]);
            Gereja::withoutGlobalScopes()
                ->where('id', $gerejaId)
                ->update(['status_langganan' => $status]);
        }

        if (in_array($status, ['expired', 'canceled'])) {
            $isBillingPath = str_contains($request->path(), 'langganan')
                          || str_contains($request->path(), 'tagihan');

            // Izinkan GET (read-only) dan rute billing; tolak semua mutasi
            if (! $request->isMethod('GET') && ! $isBillingPath) {
                return response()->json([
                    'success'          => false,
                    'message'          => 'Langganan telah berakhir. Silakan perbarui langganan untuk melanjutkan.',
                    'status_langganan' => $status,
                ], 402);
            }
        }

        $response = $next($request);

        // Sisipkan header status langganan agar FE bisa tampilkan banner
        $response->headers->set('X-Subscription-Status', $status);

        if ($status === 'past_due') {
            $response->headers->set('X-Subscription-Warning', 'past_due');
        } elseif ($status === 'trial') {
            $sisaHari = $langganan->trialSisaHari();
            if ($sisaHari !== null && $sisaHari <= 7) {
                $response->headers->set('X-Trial-Days-Left', (string) $sisaHari);
            }
        }

        return $response;
    }
}
