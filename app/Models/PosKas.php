<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;

class PosKas extends Model
{
    use BelongsToGereja;

    protected $table     = 'pos_kas';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'nama', 'tipe', 'nama_bank',
        'nomor_rekening', 'saldo_awal', 'is_aktif', 'urutan',
    ];

    protected $casts = [
        'saldo_awal' => 'integer',
        'is_aktif'   => 'boolean',
        'urutan'     => 'integer',
    ];

    /**
     * Saldo terkini pos (cash basis — KP-2).
     *   saldo_awal
     *   + pemasukan (pos ini, status_kas=sudah_diterima)
     *   − pengeluaran (pos ini, status_kas=sudah_dikeluarkan)
     *   + mutasi masuk (pos_tujuan = ini)
     *   − mutasi keluar (pos_asal = ini, termasuk biaya_admin)
     * Entry yang sudah dikoreksi (reversed_by != null) tidak dihitung,
     * tapi entry pembalik (reverses != null) ikut → netto otomatis benar.
     */
    public function saldo(): int
    {
        $masuk = (int) Pemasukan::where('gereja_id', $this->gereja_id)
            ->where('pos_kas_id', $this->id)
            ->where('status_kas', 'sudah_diterima')
            ->whereNull('reversed_by')->whereNull('reverses')
            ->sum('jumlah');

        $keluar = (int) Pengeluaran::where('gereja_id', $this->gereja_id)
            ->where('pos_kas_id', $this->id)
            ->where('status_kas', 'sudah_dikeluarkan')
            ->whereNull('reversed_by')->whereNull('reverses')
            ->sum('jumlah');

        $mutasiMasuk = (int) MutasiKas::where('gereja_id', $this->gereja_id)
            ->where('pos_tujuan_id', $this->id)
            ->whereNull('reversed_by')->whereNull('reverses')
            ->sum('jumlah');

        $mutasiKeluar = (int) MutasiKas::where('gereja_id', $this->gereja_id)
            ->where('pos_asal_id', $this->id)
            ->whereNull('reversed_by')->whereNull('reverses')
            ->selectRaw('COALESCE(SUM(jumlah + biaya_admin), 0) as total')
            ->value('total');

        return $this->saldo_awal + $masuk - $keluar + $mutasiMasuk - $mutasiKeluar;
    }
}
