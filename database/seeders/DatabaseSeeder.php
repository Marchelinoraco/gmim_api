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

        Gereja::query()->upsert([
            ['id' => 'gmim-bethesda', 'nama' => 'GMIM Bethesda', 'alamat' => 'Jl. Raya Manado No. 10, Manado', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'gmim-eben-haezer', 'nama' => 'GMIM Eben Haezer', 'alamat' => 'Jl. Sam Ratulangi No. 25, Tomohon', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'gmim-eben-haezer-tumpaan-1', 'nama' => 'GMIM Eben Haezer Tumpaan 1', 'alamat' => 'Tumpaan 1', 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);

        ChurchUser::query()->upsert([
            ['id' => 'user-001', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Bpk. Yohanes Manoppo', 'role' => 'Bendahara', 'username' => 'bendahara-bethesda', 'password' => Hash::make('bendahara123'), 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'user-002', 'gereja_id' => 'gmim-eben-haezer', 'nama' => 'Ibu. Maria Wenas', 'role' => 'Bendahara', 'username' => 'bendahara-eben-haezer', 'password' => Hash::make('bendahara123'), 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'user-003', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Pnt. Markus Roring', 'role' => 'Pelayan Khusus', 'username' => 'pelayan-bethesda', 'password' => Hash::make('pelayan123'), 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'user-004', 'gereja_id' => 'gmim-eben-haezer', 'nama' => 'Pnt. Grace Wenas', 'role' => 'Pelayan Khusus', 'username' => 'pelayan-eben-haezer', 'password' => Hash::make('pelayan123'), 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'user-005', 'gereja_id' => 'gmim-eben-haezer-tumpaan-1', 'nama' => 'Bendahara GMIM Eben Haezer Tumpaan 1', 'role' => 'Bendahara', 'username' => 'bendahara-tumpaan-1', 'password' => Hash::make('bendahara123'), 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);

        KategoriPersembahan::query()->upsert([
            ['id' => 'kat-pers-001', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Persembahan Minggu', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-002', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Persembahan Diakonia', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-003', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Persembahan Pembangunan', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-004', 'gereja_id' => 'gmim-eben-haezer', 'nama' => 'Persembahan Minggu', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-pers-005', 'gereja_id' => 'gmim-eben-haezer', 'nama' => 'Persembahan Syukur', 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);

        NamaPersembahan::query()->upsert([
            ['id' => 'nama-pers-001', 'gereja_id' => 'gmim-bethesda', 'kategori_persembahan_id' => 'kat-pers-001', 'nama' => 'Persembahan Minggu Pagi', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-002', 'gereja_id' => 'gmim-bethesda', 'kategori_persembahan_id' => 'kat-pers-001', 'nama' => 'Persembahan Minggu Sore', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-003', 'gereja_id' => 'gmim-bethesda', 'kategori_persembahan_id' => 'kat-pers-002', 'nama' => 'Persembahan Diakonia Umum', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-004', 'gereja_id' => 'gmim-bethesda', 'kategori_persembahan_id' => 'kat-pers-003', 'nama' => 'Persembahan Pembangunan Gedung', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-005', 'gereja_id' => 'gmim-eben-haezer', 'kategori_persembahan_id' => 'kat-pers-004', 'nama' => 'Persembahan Minggu Pagi', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'nama-pers-006', 'gereja_id' => 'gmim-eben-haezer', 'kategori_persembahan_id' => 'kat-pers-005', 'nama' => 'Persembahan Syukur Keluarga', 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);

        KategoriPengeluaran::query()->upsert([
            ['id' => 'kat-keluar-001', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Operasional', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-002', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Pembangunan', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-003', 'gereja_id' => 'gmim-bethesda', 'nama' => 'Sosial', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-004', 'gereja_id' => 'gmim-eben-haezer', 'nama' => 'Operasional', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kat-keluar-005', 'gereja_id' => 'gmim-eben-haezer', 'nama' => 'Diakonia', 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);

        Pemasukan::query()->upsert([
            ['id' => 'pem-001', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-06', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-001', 'jumlah' => 2500000, 'keterangan' => 'Ibadah Minggu Pagi', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-002', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-06', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-002', 'jumlah' => 1800000, 'keterangan' => 'Ibadah Minggu Sore', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-003', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-13', 'kategori_persembahan_id' => 'kat-pers-001', 'nama_persembahan_id' => 'nama-pers-001', 'jumlah' => 3000000, 'keterangan' => 'Ibadah Minggu Pagi', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-004', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-13', 'kategori_persembahan_id' => 'kat-pers-002', 'nama_persembahan_id' => 'nama-pers-003', 'jumlah' => 750000, 'keterangan' => 'Persembahan diakonia ibadah minggu', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-005', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-06-29', 'kategori_persembahan_id' => 'kat-pers-003', 'nama_persembahan_id' => 'nama-pers-004', 'jumlah' => 5000000, 'keterangan' => 'Persembahan pembangunan gedung baru', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-006', 'gereja_id' => 'gmim-eben-haezer', 'tanggal' => '2025-07-06', 'kategori_persembahan_id' => 'kat-pers-004', 'nama_persembahan_id' => 'nama-pers-005', 'jumlah' => 1200000, 'keterangan' => 'Ibadah Minggu Pagi', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'pem-007', 'gereja_id' => 'gmim-eben-haezer', 'tanggal' => '2025-07-13', 'kategori_persembahan_id' => 'kat-pers-005', 'nama_persembahan_id' => 'nama-pers-006', 'jumlah' => 800000, 'keterangan' => 'Persembahan syukur keluarga Mamahit', 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);

        Pengeluaran::query()->upsert([
            ['id' => 'kel-001', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-07', 'kategori_pengeluaran_id' => 'kat-keluar-001', 'jumlah' => 500000, 'keterangan' => 'Bayar listrik gereja', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-002', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-08', 'kategori_pengeluaran_id' => 'kat-keluar-001', 'jumlah' => 300000, 'keterangan' => 'Bayar air PDAM', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-003', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-10', 'kategori_pengeluaran_id' => 'kat-keluar-002', 'jumlah' => 2000000, 'keterangan' => 'Pembelian material renovasi atap', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-004', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-07-12', 'kategori_pengeluaran_id' => 'kat-keluar-003', 'jumlah' => 1000000, 'keterangan' => 'Bantuan sosial jemaat sakit', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-005', 'gereja_id' => 'gmim-bethesda', 'tanggal' => '2025-06-30', 'kategori_pengeluaran_id' => 'kat-keluar-001', 'jumlah' => 750000, 'keterangan' => 'Pembelian perlengkapan ibadah', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-006', 'gereja_id' => 'gmim-eben-haezer', 'tanggal' => '2025-07-05', 'kategori_pengeluaran_id' => 'kat-keluar-004', 'jumlah' => 400000, 'keterangan' => 'Bayar listrik gereja', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 'kel-007', 'gereja_id' => 'gmim-eben-haezer', 'tanggal' => '2025-07-10', 'kategori_pengeluaran_id' => 'kat-keluar-005', 'jumlah' => 600000, 'keterangan' => 'Bantuan duka cita keluarga Wenas', 'created_at' => $time, 'updated_at' => $time],
        ], ['id']);
    }
}
