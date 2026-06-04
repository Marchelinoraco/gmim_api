<?php

namespace Database\Seeders;

use App\Models\GerejaMidtrans;
use Illuminate\Database\Seeder;

class GerejaMidtransSeeder extends Seeder
{
    public function run(): void
    {
        // Key sandbox untuk development — ganti dengan key production saat go-live
        // server_key dienkripsi otomatis oleh cast 'encrypted' di model
        GerejaMidtrans::updateOrCreate(
            ['gereja_id' => 'gmim-bethesda'],
            [
                'server_key'    => 'SB-Mid-server-VuSIwZNa6ogfiFfSuZHuZwoA',
                'client_key'    => 'SB-Mid-client-3CPHJ1l74THB1o7V',
                'merchant_id'   => null,
                'is_production' => false,
                'is_active'     => true,
            ]
        );
    }
}
