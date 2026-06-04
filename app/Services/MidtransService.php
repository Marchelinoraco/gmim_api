<?php

namespace App\Services;

use App\Models\GerejaMidtrans;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    private GerejaMidtrans $config;

    public function __construct(GerejaMidtrans $config)
    {
        $this->config = $config;
    }

    /**
     * Load konfigurasi Midtrans untuk gereja ini ke library Midtrans.
     * Dipanggil sekali sebelum setiap operasi Midtrans.
     */
    private function configure(): void
    {
        Config::$serverKey    = $this->config->server_key; // didekripsi otomatis oleh cast
        Config::$isProduction = $this->config->is_production;
        Config::$isSanitized  = true;
        Config::$is3ds        = true;
    }

    /**
     * Buat Snap token untuk transaksi baru.
     *
     * @param  string  $orderId    ID unik transaksi (pemasukan.id)
     * @param  int     $amount     Jumlah dalam rupiah (integer)
     * @param  array   $details    ['nama' => ..., 'keterangan' => ...]
     * @return string  Snap token
     */
    public function createSnapToken(string $orderId, int $amount, array $details): string
    {
        $this->configure();

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'item_details' => [
                [
                    'id'       => $orderId,
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => $details['keterangan'] ?: 'Persembahan',
                ],
            ],
            'customer_details' => [
                'first_name' => $details['nama'] ?? 'Jemaat',
            ],
        ];

        return Snap::getSnapToken($params);
    }

    /**
     * Verifikasi signature_key dari webhook Midtrans.
     * Format: SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function verifySignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $incomingSignature
    ): bool {
        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->config->server_key);

        return hash_equals($expected, $incomingSignature);
    }

    /**
     * Factory: buat instance MidtransService dari gereja_id.
     * Return null jika gereja tidak punya konfigurasi Midtrans aktif.
     */
    public static function forGereja(string $gerejaId): ?self
    {
        $config = GerejaMidtrans::where('gereja_id', $gerejaId)
            ->where('is_active', true)
            ->first();

        return $config ? new self($config) : null;
    }
}
