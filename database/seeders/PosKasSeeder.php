<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed 3 pos kas default untuk tiap gereja + backfill transaksi lama.
 * Backfill cerdas: pemasukan midtrans/settled → pos Midtrans, sisanya → Tunai.
 */
class PosKasSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $gerejaIds = DB::table('gereja')->pluck('id');

        foreach ($gerejaIds as $gerejaId) {
            // Lewati jika sudah punya pos (idempoten)
            if (DB::table('pos_kas')->where('gereja_id', $gerejaId)->exists()) {
                continue;
            }

            $tunaiId    = 'pos-' . Str::ulid();
            $bankId     = 'pos-' . Str::ulid();
            $midtransId = 'pos-' . Str::ulid();

            DB::table('pos_kas')->insert([
                ['id' => $tunaiId,    'gereja_id' => $gerejaId, 'nama' => 'Tunai',        'tipe' => 'tunai',    'nama_bank' => null,           'saldo_awal' => 0, 'is_aktif' => true, 'urutan' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => $bankId,     'gereja_id' => $gerejaId, 'nama' => 'Rekening BSG', 'tipe' => 'bank',     'nama_bank' => 'Bank Sulut Go', 'saldo_awal' => 0, 'is_aktif' => true, 'urutan' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['id' => $midtransId, 'gereja_id' => $gerejaId, 'nama' => 'Midtrans',     'tipe' => 'midtrans', 'nama_bank' => null,           'saldo_awal' => 0, 'is_aktif' => true, 'urutan' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);

            // Backfill pemasukan: midtrans/settled → Midtrans, sisanya → Tunai
            DB::table('pemasukan')->where('gereja_id', $gerejaId)
                ->where('sumber', 'midtrans')
                ->whereNull('pos_kas_id')
                ->update(['pos_kas_id' => $midtransId]);

            DB::table('pemasukan')->where('gereja_id', $gerejaId)
                ->whereNull('pos_kas_id')
                ->update(['pos_kas_id' => $tunaiId]);

            // Backfill pengeluaran lama → Tunai
            DB::table('pengeluaran')->where('gereja_id', $gerejaId)
                ->whereNull('pos_kas_id')
                ->update(['pos_kas_id' => $tunaiId]);
        }
    }
}
