<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pemasukan extends Model
{
    use BelongsToGereja, SoftDeletes;

    protected $table = 'pemasukan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'pos_kas_id', 'sumber', 'status',
        'status_kas', 'tanggal_diterima',
        'tanggal', 'kategori_persembahan_id', 'nama_persembahan_id',
        'jumlah', 'keterangan',
        'bukti_gambar', 'input_by', 'approved_by', 'approved_at', 'rejected_reason',
        'midtrans_order_id', 'midtrans_transaction_id', 'payment_type', 'settled_at',
        'reverses', 'reversed_by', 'alasan_koreksi',
    ];

    protected $casts = [
        'tanggal'          => 'date:Y-m-d',
        'tanggal_diterima' => 'date:Y-m-d',
        'jumlah'           => 'integer',
        'approved_at'      => 'datetime',
    ];

    // Singkirkan transaksi yang dikoreksi (asli) + entry pembalik (F-6) → netto benar
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('reversed_by')->whereNull('reverses');
    }

    // Disetujui (approved/settled) — basis approval, belum tentu sudah jadi kas
    public function scopeCounted(Builder $query): Builder
    {
        return $query->aktif()->where(function (Builder $q) {
            $q->where(fn (Builder $q) => $q->where('sumber', 'manual')->where('status', 'approved'))
              ->orWhere(fn (Builder $q) => $q->where('sumber', 'midtrans')->where('status', 'settled'));
        });
    }

    // Cash basis (F-1): sudah disetujui DAN kas benar-benar diterima
    public function scopeCash(Builder $query): Builder
    {
        return $query->counted()->where('status_kas', 'sudah_diterima');
    }

    // Sudah disetujui tapi kas belum diterima (untuk menu F-4)
    public function scopeBelumDiterima(Builder $query): Builder
    {
        return $query->counted()->where('status_kas', 'belum_diterima');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('sumber', 'manual')->where('status', 'pending');
    }

    public function posKas()
    {
        return $this->belongsTo(PosKas::class, 'pos_kas_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(ChurchUser::class, 'input_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(ChurchUser::class, 'approved_by');
    }
}
