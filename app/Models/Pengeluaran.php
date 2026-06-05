<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    use BelongsToGereja;
    protected $table = 'pengeluaran';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'pos_kas_id', 'tanggal', 'kategori_pengeluaran_id',
        'jumlah', 'keterangan', 'status_kas', 'tanggal_dikeluarkan',
        'reverses', 'reversed_by', 'alasan_koreksi',
    ];

    protected $casts = [
        'tanggal'             => 'date:Y-m-d',
        'tanggal_dikeluarkan' => 'date:Y-m-d',
        'jumlah'              => 'integer',
    ];

    // Singkirkan yang dikoreksi + entry pembalik (F-6)
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('reversed_by')->whereNull('reverses');
    }

    // Cash basis (F-1): pengeluaran yang kasnya benar-benar sudah keluar
    public function scopeCash(Builder $query): Builder
    {
        return $query->aktif()->where('status_kas', 'sudah_dikeluarkan');
    }

    public function scopeBelumDikeluarkan(Builder $query): Builder
    {
        return $query->aktif()->where('status_kas', 'belum_dikeluarkan');
    }

    public function posKas()
    {
        return $this->belongsTo(PosKas::class, 'pos_kas_id');
    }
}
