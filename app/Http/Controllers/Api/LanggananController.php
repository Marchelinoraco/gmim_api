<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Langganan;
use App\Models\Paket;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LanggananController extends Controller
{
    public function show(string $gereja)
    {
        $langganan = Langganan::with('paket')
            ->where('gereja_id', $gereja)
            ->first();

        if (! $langganan) {
            return response()->json(['success' => false, 'message' => 'Data langganan tidak ditemukan.'], 404);
        }

        $statusEfektif = $langganan->statusEfektif();
        $sisaHari      = $langganan->trialSisaHari();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $langganan->id,
                'status'        => $statusEfektif,
                'siklus'        => $langganan->siklus,
                'trialBerakhir' => $langganan->trial_berakhir?->toDateString(),
                'mulai'         => $langganan->mulai?->toDateString(),
                'berakhir'      => $langganan->berakhir?->toDateString(),
                'autoRenew'     => $langganan->auto_renew,
                'trialSisaHari' => $sisaHari,
                'paket'         => $langganan->paket ? [
                    'id'           => $langganan->paket->id,
                    'nama'         => $langganan->paket->nama,
                    'hargaBulanan' => $langganan->paket->harga_bulanan,
                    'hargaTahunan' => $langganan->paket->harga_tahunan,
                    'batas'        => $langganan->paket->batas,
                ] : null,
            ],
        ]);
    }

    public function tagihanIndex(string $gereja)
    {
        $tagihan = Tagihan::where('gereja_id', $gereja)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'nomor'      => $t->nomor,
                'periode'    => $t->periode,
                'jumlah'     => $t->jumlah,
                'status'     => $t->status,
                'jatuhTempo' => $t->jatuh_tempo?->toDateString(),
                'dibayarPada'=> $t->dibayar_pada?->toDateTimeString(),
                'snapToken'  => $t->snap_token,
            ]);

        return response()->json(['success' => true, 'data' => $tagihan]);
    }

    public function daftarPaket()
    {
        $paket = Paket::where('is_active', true)->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'nama'         => $p->nama,
                'hargaBulanan' => $p->harga_bulanan,
                'hargaTahunan' => $p->harga_tahunan,
                'batas'        => $p->batas,
            ]);

        return response()->json(['success' => true, 'data' => $paket]);
    }

    public function handleBillingWebhook(Request $request)
    {
        // Webhook Midtrans akun PLATFORM — diimplementasi saat Midtrans platform dikonfigurasi
        $orderId = $request->input('order_id', '');

        if (! str_starts_with($orderId, 'SUB-')) {
            return response()->json(['message' => 'Bukan notifikasi billing.'], 400);
        }

        $transactionStatus = $request->input('transaction_status');
        $fraudStatus       = $request->input('fraud_status', 'accept');

        if ($transactionStatus === 'settlement' && $fraudStatus === 'accept') {
            $tagihan = Tagihan::where('midtrans_order_id', $orderId)->first();

            if ($tagihan && $tagihan->status === 'unpaid') {
                $tagihan->update([
                    'status'      => 'paid',
                    'dibayar_pada' => now(),
                ]);

                // Perpanjang langganan 1 bulan / 1 tahun
                $langganan = $tagihan->langganan;
                if ($langganan) {
                    $base     = $langganan->berakhir && $langganan->berakhir->isFuture()
                                ? $langganan->berakhir
                                : now();
                    $newEnd   = $langganan->siklus === 'tahunan'
                                ? $base->addYear()
                                : $base->addMonth();
                    $langganan->update([
                        'status'   => 'active',
                        'berakhir' => $newEnd->toDateString(),
                        'paket_id' => $tagihan->langganan->paket_id,
                    ]);
                }
            }
        }

        return response()->json(['message' => 'OK']);
    }

    public function checkout(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'paket_id' => ['required', 'string', 'exists:paket,id'],
            'siklus'   => ['required', 'in:bulanan,tahunan'],
        ]);

        $langganan = Langganan::where('gereja_id', $gereja)->first();
        if (! $langganan) {
            return response()->json(['success' => false, 'message' => 'Data langganan tidak ditemukan.'], 404);
        }

        $paket    = Paket::find($validated['paket_id']);
        $jumlah   = $validated['siklus'] === 'tahunan' ? $paket->harga_tahunan : $paket->harga_bulanan;
        $periode  = now()->format('Y-m');
        $nomor    = 'INV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        $orderId  = 'SUB-' . strtoupper(Str::ulid());

        // Buat tagihan
        $tagihan = Tagihan::create([
            'id'              => 'tag-' . Str::ulid(),
            'gereja_id'       => $gereja,
            'langganan_id'    => $langganan->id,
            'nomor'           => $nomor,
            'periode'         => $periode,
            'jumlah'          => $jumlah,
            'status'          => 'unpaid',
            'jatuh_tempo'     => now()->addDays(7)->toDateString(),
            'midtrans_order_id' => $orderId,
        ]);

        // Midtrans platform belum dikonfigurasi — kembalikan tagihan + flag
        // Snap token akan diisi oleh webhook/konfigurasi Midtrans platform
        return response()->json([
            'success'        => true,
            'message'        => 'Tagihan berhasil dibuat. Integrasi pembayaran akan segera tersedia.',
            'data'           => [
                'tagihanId'  => $tagihan->id,
                'nomor'      => $tagihan->nomor,
                'jumlah'     => $tagihan->jumlah,
                'orderId'    => $orderId,
                'snapToken'  => null,
                'snapReady'  => false,
            ],
        ], 201);
    }
}
