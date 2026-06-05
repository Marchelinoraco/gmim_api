<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $time = now();

        // ----------------------------------------------------------------
        // PAKET
        // ----------------------------------------------------------------
        DB::table('paket')->upsert([
            [
                'id'             => 'paket-basic',
                'nama'           => 'Basic',
                'harga_bulanan'  => 99000,
                'harga_tahunan'  => 999000,
                'batas'          => json_encode(['max_pengguna' => 3]),
                'is_active'      => true,
                'created_at'     => $time,
                'updated_at'     => $time,
            ],
            [
                'id'             => 'paket-standard',
                'nama'           => 'Standard',
                'harga_bulanan'  => 199000,
                'harga_tahunan'  => 1999000,
                'batas'          => json_encode(['max_pengguna' => 10, 'fitur' => ['aset', 'gaji']]),
                'is_active'      => true,
                'created_at'     => $time,
                'updated_at'     => $time,
            ],
            [
                'id'             => 'paket-premium',
                'nama'           => 'Premium',
                'harga_bulanan'  => 399000,
                'harga_tahunan'  => 3999000,
                'batas'          => json_encode(['max_pengguna' => 50, 'fitur' => ['aset', 'gaji', 'midtrans', 'ekspor']]),
                'is_active'      => true,
                'created_at'     => $time,
                'updated_at'     => $time,
            ],
        ], ['id'], ['nama', 'harga_bulanan', 'harga_tahunan', 'batas', 'is_active', 'updated_at']);

        // ----------------------------------------------------------------
        // LANGGANAN — seed untuk gereja yang sudah ada
        // ----------------------------------------------------------------
        DB::table('langganan')->upsert([
            [
                'id'             => 'sub-bethesda',
                'gereja_id'      => 'gmim-bethesda',
                'paket_id'       => 'paket-standard',
                'status'         => 'active',
                'siklus'         => 'bulanan',
                'trial_berakhir' => null,
                'mulai'          => '2026-01-01',
                'berakhir'       => '2027-01-01',
                'auto_renew'     => true,
                'created_at'     => $time,
                'updated_at'     => $time,
            ],
            [
                'id'             => 'sub-eben-haezer',
                'gereja_id'      => 'gmim-eben-haezer',
                'paket_id'       => 'paket-basic',
                'status'         => 'active',
                'siklus'         => 'bulanan',
                'trial_berakhir' => null,
                'mulai'          => '2026-01-01',
                'berakhir'       => '2026-12-31',
                'auto_renew'     => true,
                'created_at'     => $time,
                'updated_at'     => $time,
            ],
            [
                'id'             => 'sub-tumpaan-1',
                'gereja_id'      => 'gmim-eben-haezer-tumpaan-1',
                'paket_id'       => 'paket-basic',
                'status'         => 'trial',
                'siklus'         => 'bulanan',
                'trial_berakhir' => '2026-06-19',
                'mulai'          => null,
                'berakhir'       => null,
                'auto_renew'     => true,
                'created_at'     => $time,
                'updated_at'     => $time,
            ],
        ], ['id'], ['paket_id', 'status', 'siklus', 'trial_berakhir', 'mulai', 'berakhir', 'auto_renew', 'updated_at']);
    }
}
