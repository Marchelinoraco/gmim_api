<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

class MidtransConfigController extends Controller
{
    public function show(string $gereja)
    {
        $config = DB::table('gereja_midtrans')->where('gereja_id', $gereja)->first();

        if (! $config) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'clientKey'    => $config->client_key,
                'merchantId'   => $config->merchant_id,
                'isProduction' => (bool) $config->is_production,
                'isActive'     => (bool) $config->is_active,
                'serverKeyMasked' => $this->maskServerKey($config->server_key),
            ],
        ]);
    }

    public function update(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'serverKey'    => ['required_without:keepServerKey', 'string', 'min:10'],
            'keepServerKey'=> ['sometimes', 'boolean'],
            'clientKey'    => ['required', 'string', 'min:5'],
            'merchantId'   => ['nullable', 'string'],
            'isProduction' => ['required', 'boolean'],
        ]);

        $existing = DB::table('gereja_midtrans')->where('gereja_id', $gereja)->first();

        // Jika keepServerKey=true dan ada data existing, gunakan server_key lama
        $serverKeyEncrypted = $existing?->server_key;
        if (! ($validated['keepServerKey'] ?? false)) {
            $serverKeyEncrypted = Crypt::encryptString($validated['serverKey']);
        }

        $data = [
            'client_key'    => $validated['clientKey'],
            'merchant_id'   => $validated['merchantId'] ?? null,
            'is_production' => $validated['isProduction'],
            'is_active'     => true,
            'updated_at'    => now(),
        ];

        if ($existing) {
            DB::table('gereja_midtrans')->where('gereja_id', $gereja)->update(
                array_merge($data, ['server_key' => $serverKeyEncrypted])
            );
        } else {
            DB::table('gereja_midtrans')->insert(array_merge($data, [
                'gereja_id'  => $gereja,
                'server_key' => $serverKeyEncrypted,
                'created_at' => now(),
            ]));
        }

        return response()->json(['success' => true, 'message' => 'Konfigurasi Midtrans berhasil disimpan.']);
    }

    public function test(string $gereja)
    {
        $config = DB::table('gereja_midtrans')->where('gereja_id', $gereja)->first();

        if (! $config) {
            return response()->json(['success' => false, 'message' => 'Konfigurasi Midtrans belum diatur.'], 422);
        }

        try {
            $serverKey = Crypt::decryptString($config->server_key);

            MidtransConfig::$serverKey     = $serverKey;
            MidtransConfig::$clientKey     = $config->client_key;
            MidtransConfig::$isProduction  = (bool) $config->is_production;
            MidtransConfig::$isSanitized   = true;
            MidtransConfig::$is3ds         = true;

            // Test minimal: buat Snap token dengan order fiktif
            $params = [
                'transaction_details' => [
                    'order_id' => 'TEST-' . time(),
                    'gross_amount' => 10000,
                ],
            ];
            Snap::getSnapToken($params);

            return response()->json(['success' => true, 'message' => 'Koneksi Midtrans berhasil. Konfigurasi valid.']);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi gagal: ' . $this->sanitizeMidtransError($e->getMessage()),
            ], 422);
        }
    }

    /** Masking server_key: hanya tampilkan 6 karakter pertama + *** */
    private function maskServerKey(string $encrypted): string
    {
        try {
            $key = Crypt::decryptString($encrypted);
            return substr($key, 0, 6) . str_repeat('*', max(0, strlen($key) - 6));
        } catch (\Throwable) {
            return '***';
        }
    }

    /** Bersihkan pesan error Midtrans agar tidak expose info sensitif */
    private function sanitizeMidtransError(string $message): string
    {
        // Hapus URL, token, dan detail teknis
        $message = preg_replace('/https?:\/\/\S+/i', '[URL]', $message);
        $message = preg_replace('/[A-Za-z0-9]{32,}/', '[token]', $message);
        return $message;
    }
}
