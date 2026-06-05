<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;

class PeriodeBuku extends Model
{
    use BelongsToGereja;

    protected $table = 'periode_buku';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'tipe', 'periode',
        'saldo_awal', 'total_pemasukan', 'total_pengeluaran', 'saldo_akhir',
        'status', 'closed_by', 'closed_at', 'catatan',
    ];

    protected $casts = [
        'saldo_awal'        => 'integer',
        'total_pemasukan'   => 'integer',
        'total_pengeluaran' => 'integer',
        'saldo_akhir'       => 'integer',
        'closed_at'         => 'datetime',
    ];
}
