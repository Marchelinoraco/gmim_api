<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GerejaMidtrans;
use App\Models\KategoriPersembahan;
use App\Models\NamaPersembahan;
use App\Models\Pemasukan;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MidtransController extends Controller
{
    // -----------------------------------------------------------------------
    // POST /gereja/{gereja}/pemasukan/midtrans
    // Staf buat order Midtrans → dapat Snap token untuk ditampilkan ke jemaat
    // -----------------------------------------------------------------------

    public function createPayment(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'tanggal'               => ['required', 'date', 'before_or_equal:today'],
            'kategoriPersembahanId' => ['required', 'string'],
            'namaPersembahanId'     => ['required', 'string'],
            'jumlah'                => ['required', 'integer', 'min:1'],
            'keterangan'            => ['nullable', 'string', 'max:255'],
        ]);

        // Validasi relasi kategori & nama persembahan milik gereja ini
        $kategori = KategoriPersembahan::where('gereja_id', $gereja)
            ->whereKey($validated['kategoriPersembahanId'])->first();
        abort_if(! $kategori, 404, 'Kategori tidak ditemukan.');

        $nama = NamaPersembahan::where('gereja_id', $gereja)
            ->whereKey($validated['namaPersembahanId'])->first();
        abort_if(! $nama, 404, 'Nama persembahan tidak ditemukan.');
        abort_if($nama->kategori_persembahan_id !== $kategori->id, 422, 'Nama persembahan tidak sesuai kategori.');

        // Pastikan gereja punya konfigurasi Midtrans aktif
        $midtrans = MidtransService::forGereja($gereja);
        if (! $midtrans) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran online belum dikonfigurasi untuk gereja ini.',
            ], 422);
        }

        // Buat record pemasukan dengan status pending_payment
        $orderId = 'pem-mid-' . Str::ulid();
        $user    = $request->user();

        // Pemasukan online masuk ke pos Midtrans (F-5)
        $posMidtrans = \App\Models\PosKas::where('gereja_id', $gereja)->where('tipe', 'midtrans')->first();

        $pemasukan = Pemasukan::create([
            'id'                      => $orderId,
            'gereja_id'               => $gereja,
            'pos_kas_id'              => $posMidtrans?->id,
            'sumber'                  => 'midtrans',
            'status'                  => 'pending_payment',
            'status_kas'              => 'sudah_diterima', // settle = uang masuk saldo Midtrans
            'tanggal'                 => $validated['tanggal'],
            'tanggal_diterima'        => $validated['tanggal'],
            'kategori_persembahan_id' => $validated['kategoriPersembahanId'],
            'nama_persembahan_id'     => $validated['namaPersembahanId'],
            'jumlah'                  => $validated['jumlah'],
            'keterangan'              => $validated['keterangan'] ?? '',
            'input_by'                => $user->id,
            'midtrans_order_id'       => $orderId,
        ]);

        // Buat Snap token
        try {
            $snapToken = $midtrans->createSnapToken(
                orderId: $orderId,
                amount: $validated['jumlah'],
                details: [
                    'nama'        => $user->nama,
                    'keterangan'  => $validated['keterangan'] ?? $kategori->nama . ' - ' . $nama->nama,
                ]
            );
        } catch (\Exception $e) {
            // Rollback record jika Snap token gagal dibuat
            $pemasukan->delete();
            Log::error('Midtrans Snap token error', ['gereja' => $gereja, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat transaksi pembayaran. Silakan coba lagi.',
            ], 500);
        }

        // Ambil client_key + is_production untuk frontend
        $midtransConfig = GerejaMidtrans::where('gereja_id', $gereja)->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'pemasukanId'  => $pemasukan->id,
                'orderId'      => $orderId,
                'snapToken'    => $snapToken,
                'clientKey'    => $midtransConfig?->client_key,
                'isProduction' => $midtransConfig?->is_production ?? false,
                'jumlah'       => $validated['jumlah'],
            ],
        ], 201);
    }

    // -----------------------------------------------------------------------
    // POST /midtrans/notification  (publik — dipanggil Midtrans server)
    // Webhook: verifikasi signature lalu update status pemasukan
    // -----------------------------------------------------------------------

    public function handleNotification(Request $request)
    {
        $payload = $request->all();

        $orderId     = $payload['order_id']          ?? '';
        $statusCode  = $payload['status_code']       ?? '';
        $grossAmount = $payload['gross_amount']       ?? '';
        $signature   = $payload['signature_key']     ?? '';
        $txStatus    = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status']       ?? '';
        $txId        = $payload['transaction_id']     ?? '';
        $paymentType = $payload['payment_type']       ?? '';

        // Cari record pemasukan berdasarkan order_id
        $pemasukan = Pemasukan::where('midtrans_order_id', $orderId)->first();

        if (! $pemasukan) {
            // Midtrans mengharapkan 200 bahkan jika order tidak ditemukan
            Log::warning('Midtrans webhook: order tidak ditemukan', ['order_id' => $orderId]);
            return response()->json(['success' => true]);
        }

        // Verifikasi signature menggunakan server_key gereja terkait
        $midtrans = MidtransService::forGereja($pemasukan->gereja_id);

        if (! $midtrans || ! $midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signature)) {
            Log::warning('Midtrans webhook: signature tidak valid', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        // Idempoten: jika sudah settled/expired/failed, abaikan duplikat
        if (in_array($pemasukan->status, ['settled', 'expired', 'failed'])) {
            return response()->json(['success' => true]);
        }

        // Tentukan status baru berdasarkan notifikasi Midtrans
        $newStatus = $this->resolveStatus($txStatus, $fraudStatus);

        $updateData = [
            'status'                 => $newStatus,
            'midtrans_transaction_id' => $txId ?: $pemasukan->midtrans_transaction_id,
            'payment_type'           => $paymentType ?: $pemasukan->payment_type,
        ];

        if ($newStatus === 'settled') {
            $updateData['settled_at'] = now();
        }

        $pemasukan->update($updateData);

        Log::info('Midtrans webhook diproses', [
            'order_id'   => $orderId,
            'tx_status'  => $txStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------------
    // DELETE /gereja/{gereja}/pemasukan/{id}/cancel-payment
    // Batalkan transaksi Midtrans yang masih pending_payment
    // Dipanggil frontend saat user tutup popup tanpa bayar
    // -----------------------------------------------------------------------

    public function cancelPayment(Request $request, string $gereja, string $id)
    {
        $pemasukan = Pemasukan::where('gereja_id', $gereja)
            ->where('midtrans_order_id', $id)
            ->orWhere(fn ($q) => $q->where('gereja_id', $gereja)->whereKey($id))
            ->first();

        if (! $pemasukan) {
            return response()->json(['success' => true]); // idempoten
        }

        // Hanya boleh cancel yang masih pending_payment
        if ($pemasukan->status !== 'pending_payment') {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak dapat dibatalkan.'], 422);
        }

        // Pastikan hanya user yang membuat yang bisa cancel
        if ($pemasukan->input_by !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $pemasukan->delete(); // soft delete

        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------------
    // GET /gereja/{gereja}/midtrans/client-key
    // Frontend ambil client_key untuk load Snap.js
    // -----------------------------------------------------------------------

    public function clientKey(string $gereja)
    {
        $config = GerejaMidtrans::where('gereja_id', $gereja)
            ->where('is_active', true)
            ->first();

        if (! $config) {
            return response()->json(['success' => false, 'message' => 'Midtrans tidak aktif.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'clientKey'    => $config->client_key,
                'isProduction' => $config->is_production,
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Private: petakan status Midtrans → status internal
    // -----------------------------------------------------------------------

    private function resolveStatus(string $txStatus, string $fraudStatus): string
    {
        return match (true) {
            $txStatus === 'capture' && $fraudStatus === 'accept' => 'settled',
            $txStatus === 'settlement'                           => 'settled',
            $txStatus === 'pending'                              => 'pending_payment',
            in_array($txStatus, ['deny', 'cancel', 'failure'])  => 'failed',
            $txStatus === 'expire'                               => 'expired',
            default                                              => 'pending_payment',
        };
    }
}
