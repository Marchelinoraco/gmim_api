<?php

namespace Database\Seeders;

use App\Models\ChurchUser;
use App\Models\Gereja;
use App\Models\KategoriPengeluaran;
use App\Models\KategoriPersembahan;
use App\Models\NamaPersembahan;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $time = now();

        // ----------------------------------------------------------------
        // GEREJA — tambah slug (path-based KF-2) & info SaaS
        // ----------------------------------------------------------------
        Gereja::query()->upsert([
            [
                'id'               => 'gmim-bethesda',
                'nama'             => 'GMIM Bethesda',
                'slug'             => 'bethesda',
                'alamat'           => 'Jl. Raya Manado No. 10, Manado',
                'nama_pendeta'     => 'Pdt. Andreas Tumbelaka',
                'telepon'          => '0811-4300-001',
                'status_langganan' => 'active',
                'bergabung_pada'   => '2026-01-01',
                'created_at'       => $time,
                'updated_at'       => $time,
            ],
            [
                'id'               => 'gmim-eben-haezer',
                'nama'             => 'GMIM Eben Haezer',
                'slug'             => 'eben-haezer',
                'alamat'           => 'Jl. Sam Ratulangi No. 25, Tomohon',
                'nama_pendeta'     => 'Pdt. Maria Lasut',
                'telepon'          => '0811-4300-002',
                'status_langganan' => 'active',
                'bergabung_pada'   => '2026-01-01',
                'created_at'       => $time,
                'updated_at'       => $time,
            ],
            [
                'id'               => 'gmim-eben-haezer-tumpaan-1',
                'nama'             => 'GMIM Eben Haezer Tumpaan 1',
                'slug'             => 'tumpaan-1',
                'alamat'           => 'Tumpaan, Minahasa Selatan',
                'nama_pendeta'     => 'Pdt. Recky Rondonuwu',
                'telepon'          => '0811-4300-003',
                'status_langganan' => 'trial',
                'bergabung_pada'   => '2026-06-01',
                'created_at'       => $time,
                'updated_at'       => $time,
            ],
        ], ['id'], [
            'nama', 'slug', 'alamat', 'nama_pendeta', 'telepon',
            'status_langganan', 'bergabung_pada', 'updated_at',
        ]);

        // ----------------------------------------------------------------
        // PENGGUNA — tambah email (login global) + status
        // ----------------------------------------------------------------
        ChurchUser::query()->upsert([
            [
                'id'         => 'user-001',
                'gereja_id'  => 'gmim-bethesda',
                'nama'       => 'Bpk. Yohanes Manoppo',
                'email'      => 'bendahara@bethesda.gmim',
                'role'       => 'Bendahara',
                'username'   => 'bendahara-bethesda',
                'password'   => Hash::make('bendahara123'),
                'status'     => 'active',
                'created_at' => $time,
                'updated_at' => $time,
            ],
            [
                'id'         => 'user-002',
                'gereja_id'  => 'gmim-eben-haezer',
                'nama'       => 'Ibu. Maria Wenas',
                'email'      => 'bendahara@eben-haezer.gmim',
                'role'       => 'Bendahara',
                'username'   => 'bendahara-eben-haezer',
                'password'   => Hash::make('bendahara123'),
                'status'     => 'active',
                'created_at' => $time,
                'updated_at' => $time,
            ],
            [
                'id'         => 'user-003',
                'gereja_id'  => 'gmim-bethesda',
                'nama'       => 'Pnt. Markus Roring',
                'email'      => 'pelayan@bethesda.gmim',
                'role'       => 'Pelayan Khusus',
                'username'   => 'pelayan-bethesda',
                'password'   => Hash::make('pelayan123'),
                'status'     => 'active',
                'created_at' => $time,
                'updated_at' => $time,
            ],
            [
                'id'         => 'user-004',
                'gereja_id'  => 'gmim-eben-haezer',
                'nama'       => 'Pnt. Grace Wenas',
                'email'      => 'pelayan@eben-haezer.gmim',
                'role'       => 'Pelayan Khusus',
                'username'   => 'pelayan-eben-haezer',
                'password'   => Hash::make('pelayan123'),
                'status'     => 'active',
                'created_at' => $time,
                'updated_at' => $time,
            ],
            [
                'id'         => 'user-005',
                'gereja_id'  => 'gmim-eben-haezer-tumpaan-1',
                'nama'       => 'Bendahara GMIM Eben Haezer Tumpaan 1',
                'email'      => 'bendahara@tumpaan-1.gmim',
                'role'       => 'Bendahara',
                'username'   => 'bendahara-tumpaan-1',
                'password'   => Hash::make('bendahara123'),
                'status'     => 'active',
                'created_at' => $time,
                'updated_at' => $time,
            ],
            [
                'id'         => 'user-demo',
                'gereja_id'  => 'gmim-bethesda',
                'nama'       => 'Demo GMIM Bethesda',
                'email'      => 'demo@gmim.app',
                'role'       => 'Bendahara',
                'username'   => 'demo',
                'password'   => Hash::make('demo-not-used'),
                'status'     => 'active',
                'created_at' => $time,
                'updated_at' => $time,
            ],
        ], ['id'], [
            'nama', 'email', 'role', 'username', 'password', 'status', 'updated_at',
        ]);

        // ----------------------------------------------------------------
        // KATEGORI PERSEMBAHAN
        // ----------------------------------------------------------------
        KategoriPersembahan::query()->upsert([
            ['id' => 'kat-pers-001', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Persembahan Minggu',       'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-002', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Persembahan Diakonia',     'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-003', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Persembahan Pembangunan',  'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-004', 'gereja_id' => 'gmim-eben-haezer',             'nama' => 'Persembahan Minggu',       'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-005', 'gereja_id' => 'gmim-eben-haezer',             'nama' => 'Persembahan Syukur',       'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-006', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1',   'nama' => 'Persembahan Minggu',       'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-007', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1',   'nama' => 'Persembahan Pembangunan',  'created_at' => $time, 'updated_at' => $time],
        ], ['id'], ['nama', 'updated_at']);

        // ----------------------------------------------------------------
        // NAMA PERSEMBAHAN
        // ----------------------------------------------------------------
        NamaPersembahan::query()->upsert([
            ['id' => 'nama-pers-001', 'gereja_id' => 'gmim-bethesda',              'kategori_persembahan_id' => 'kat-pers-001', 'nama' => 'Persembahan Minggu Pagi',      'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-002', 'gereja_id' => 'gmim-bethesda',              'kategori_persembahan_id' => 'kat-pers-001', 'nama' => 'Persembahan Minggu Sore',      'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-003', 'gereja_id' => 'gmim-bethesda',              'kategori_persembahan_id' => 'kat-pers-002', 'nama' => 'Persembahan Diakonia Umum',    'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-004', 'gereja_id' => 'gmim-bethesda',              'kategori_persembahan_id' => 'kat-pers-003', 'nama' => 'Persembahan Pembangunan Gedung','created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-005', 'gereja_id' => 'gmim-eben-haezer',           'kategori_persembahan_id' => 'kat-pers-004', 'nama' => 'Persembahan Minggu Pagi',      'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-006', 'gereja_id' => 'gmim-eben-haezer',           'kategori_persembahan_id' => 'kat-pers-005', 'nama' => 'Persembahan Syukur Keluarga', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-007', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1', 'kategori_persembahan_id' => 'kat-pers-006', 'nama' => 'Persembahan Minggu Pagi',      'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-008', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1', 'kategori_persembahan_id' => 'kat-pers-007', 'nama' => 'Persembahan Pembangunan',      'created_at' => $time, 'updated_at' => $time],
        ], ['id'], ['nama', 'updated_at']);

        // ----------------------------------------------------------------
        // KATEGORI PENGELUARAN
        // ----------------------------------------------------------------
        KategoriPengeluaran::query()->upsert([
            ['id' => 'kat-keluar-001', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Operasional',    'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-002', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Pembangunan',    'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-003', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Sosial',         'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-004', 'gereja_id' => 'gmim-bethesda',                'nama' => 'Gaji & Honor',   'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-005', 'gereja_id' => 'gmim-eben-haezer',             'nama' => 'Operasional',    'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-006', 'gereja_id' => 'gmim-eben-haezer',             'nama' => 'Diakonia',       'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-007', 'gereja_id' => 'gmim-eben-haezer',             'nama' => 'Gaji & Honor',   'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-008', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1',   'nama' => 'Operasional',    'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-009', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1',   'nama' => 'Gaji & Honor',   'created_at' => $time, 'updated_at' => $time],
        ], ['id'], ['nama', 'updated_at']);

        // ----------------------------------------------------------------
        // PEMASUKAN — tambah sumber & status (approved agar masuk counted)
        // ----------------------------------------------------------------
        Pemasukan::query()->upsert([
            ['id' => 'pem-001', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-04', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-001', 'jumlah' => 2500000, 'keterangan' => 'Ibadah Minggu Pagi',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-002', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-04', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-002', 'jumlah' => 1800000, 'keterangan' => 'Ibadah Minggu Sore',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-003', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-11', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-001', 'jumlah' => 3000000, 'keterangan' => 'Ibadah Minggu Pagi',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-004', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-11', 'kategori_persembahan_id' => 'kat-pers-002', 'nama_persembahan_id' => 'nama-pers-003', 'jumlah' =>  750000, 'keterangan' => 'Persembahan diakonia',             'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-005', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-18', 'kategori_persembahan_id' => 'kat-pers-003', 'nama_persembahan_id' => 'nama-pers-004', 'jumlah' => 5000000, 'keterangan' => 'Persembahan pembangunan gedung',     'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-006', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-25', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-001', 'jumlah' => 2200000, 'keterangan' => 'Ibadah Minggu Pagi',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-007', 'gereja_id' => 'gmim-bethesda',    'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-06-01', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-001', 'jumlah' => 2800000, 'keterangan' => 'Ibadah Minggu Pagi',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-008', 'gereja_id' => 'gmim-eben-haezer', 'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-04', 'kategori_persembahan_id' => 'kat-pers-004', 'nama_persembahan_id' => 'nama-pers-005', 'jumlah' => 1200000, 'keterangan' => 'Ibadah Minggu Pagi',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-009', 'gereja_id' => 'gmim-eben-haezer', 'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-05-11', 'kategori_persembahan_id' => 'kat-pers-005', 'nama_persembahan_id' => 'nama-pers-006', 'jumlah' =>  800000, 'keterangan' => 'Persembahan syukur keluarga',       'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-010', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1', 'sumber' => 'manual', 'status' => 'approved', 'tanggal' => '2026-06-01', 'kategori_persembahan_id' => 'kat-pers-006', 'nama_persembahan_id' => 'nama-pers-007', 'jumlah' => 1500000, 'keterangan' => 'Ibadah Minggu Pagi', 'created_at' => $time, 'updated_at' => $time],
        ], ['id'], [
            'sumber', 'status', 'tanggal', 'kategori_persembahan_id',
            'nama_persembahan_id', 'jumlah', 'keterangan', 'updated_at',
        ]);

        // ----------------------------------------------------------------
        // PENGELUARAN
        // ----------------------------------------------------------------
        Pengeluaran::query()->upsert([
            ['id' => 'kel-001', 'gereja_id' => 'gmim-bethesda',    'tanggal' => '2026-05-05', 'kategori_pengeluaran_id' => 'kat-keluar-001', 'jumlah' =>  500000, 'keterangan' => 'Bayar listrik gereja',                'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-002', 'gereja_id' => 'gmim-bethesda',    'tanggal' => '2026-05-06', 'kategori_pengeluaran_id' => 'kat-keluar-001', 'jumlah' =>  300000, 'keterangan' => 'Bayar air PDAM',                      'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-003', 'gereja_id' => 'gmim-bethesda',    'tanggal' => '2026-05-10', 'kategori_pengeluaran_id' => 'kat-keluar-002', 'jumlah' => 2000000, 'keterangan' => 'Material renovasi atap',               'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-004', 'gereja_id' => 'gmim-bethesda',    'tanggal' => '2026-05-12', 'kategori_pengeluaran_id' => 'kat-keluar-003', 'jumlah' => 1000000, 'keterangan' => 'Bantuan sosial jemaat sakit',          'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-005', 'gereja_id' => 'gmim-bethesda',    'tanggal' => '2026-05-27', 'kategori_pengeluaran_id' => 'kat-keluar-001', 'jumlah' =>  750000, 'keterangan' => 'Perlengkapan ibadah',                 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-006', 'gereja_id' => 'gmim-eben-haezer', 'tanggal' => '2026-05-05', 'kategori_pengeluaran_id' => 'kat-keluar-005', 'jumlah' =>  400000, 'keterangan' => 'Bayar listrik gereja',                'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-007', 'gereja_id' => 'gmim-eben-haezer', 'tanggal' => '2026-05-10', 'kategori_pengeluaran_id' => 'kat-keluar-006', 'jumlah' =>  600000, 'keterangan' => 'Bantuan duka cita keluarga Wenas',    'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-008', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1', 'tanggal' => '2026-06-02', 'kategori_pengeluaran_id' => 'kat-keluar-008', 'jumlah' => 350000, 'keterangan' => 'Bayar listrik gereja',       'created_at' => $time, 'updated_at' => $time],
        ], ['id'], [
            'tanggal', 'kategori_pengeluaran_id', 'jumlah', 'keterangan', 'updated_at',
        ]);

        $this->call(GerejaMidtransSeeder::class);
        $this->call(BillingSeeder::class);
        $this->call(PlatformAdminSeeder::class);
        $this->call(PosKasSeeder::class);
    }
}
