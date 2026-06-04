<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pemasukan extends Model
{
    use SoftDeletes;

    protected $table = 'pemasukan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'sumber', 'status',
        'tanggal', 'kategori_persembahan_id', 'nama_persembahan_id',
        'jumlah', 'keterangan',
        'bukti_gambar', 'input_by', 'approved_by', 'approved_at', 'rejected_reason',
        'midtrans_order_id', 'midtrans_transaction_id', 'payment_type', 'settled_at',
    ];

    protected $casts = [
        'tanggal'     => 'date:Y-m-d',
        'jumlah'      => 'integer',
        'approved_at' => 'datetime',
    ];

    // Hanya pemasukan yang dihitung ke saldo/rekap
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(fn (Builder $q) => $q->where('sumber', 'manual')->where('status', 'approved'))
              ->orWhere(fn (Builder $q) => $q->where('sumber', 'midtrans')->where('status', 'settled'));
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('sumber', 'manual')->where('status', 'pending');
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
