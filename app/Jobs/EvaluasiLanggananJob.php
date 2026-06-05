<?php

namespace App\Jobs;

use App\Mail\LanggananPengingatMail;
use App\Models\Gereja;
use App\Models\Langganan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Job harian: evaluasi status + kirim email pengingat (H-7, H-1, H+0, H+1).
 * Jadwal: setiap hari pukul 01:00 WIB lewat Laravel Scheduler.
 */
class EvaluasiLanggananJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $this->evaluasiStatus();
        $this->kirimPengingat();
    }

    private function evaluasiStatus(): void
    {
        $now        = now()->startOfDay();
        $graceLimit = now()->subDays(Langganan::GRACE_DAYS)->startOfDay();

        // trial → expired
        Langganan::where('status', 'trial')
            ->whereNotNull('trial_berakhir')
            ->whereDate('trial_berakhir', '<', $now)
            ->update(['status' => 'expired', 'updated_at' => now()]);

        // active → past_due
        Langganan::where('status', 'active')
            ->whereNotNull('berakhir')
            ->whereDate('berakhir', '<', $now)
            ->update(['status' => 'past_due', 'updated_at' => now()]);

        // past_due → expired (grace habis)
        Langganan::where('status', 'past_due')
            ->whereNotNull('berakhir')
            ->whereDate('berakhir', '<', $graceLimit)
            ->update(['status' => 'expired', 'updated_at' => now()]);

        // Sinkronkan cache gereja.status_langganan
        Langganan::select('gereja_id', 'status')->chunk(200, function ($chunk) {
            foreach ($chunk as $sub) {
                Gereja::withoutGlobalScopes()
                    ->where('id', $sub->gereja_id)
                    ->where('status_langganan', '!=', $sub->status)
                    ->update(['status_langganan' => $sub->status]);
            }
        });
    }

    private function kirimPengingat(): void
    {
        $now = now()->startOfDay();

        // Ambil langganan aktif yang perlu notifikasi
        $targets = Langganan::with(['gereja', 'paket'])
            ->whereIn('status', ['trial', 'active', 'past_due', 'expired'])
            ->get();

        foreach ($targets as $langganan) {
            $gereja = $langganan->gereja;
            if (! $gereja || ! $gereja->email) {
                continue;
            }

            $hariSelisih = $this->hitungSelisihHari($langganan, $now);

            // Kirim hanya pada: H-7, H-1, H+0 (hari kedaluwarsa), H+1
            if (! in_array($hariSelisih, [7, 1, 0, -1])) {
                continue;
            }

            Mail::to($gereja->email)
                ->queue(new LanggananPengingatMail($langganan, $hariSelisih));
        }
    }

    /** Sisa hari sebelum berakhir (negatif = sudah lewat). */
    private function hitungSelisihHari(Langganan $langganan, \Carbon\Carbon $now): int
    {
        $tanggal = match ($langganan->status) {
            'trial'    => $langganan->trial_berakhir,
            'active',
            'past_due',
            'expired'  => $langganan->berakhir,
            default    => null,
        };

        if (! $tanggal) {
            return PHP_INT_MAX;
        }

        return (int) $now->diffInDays($tanggal, false);
    }
}
