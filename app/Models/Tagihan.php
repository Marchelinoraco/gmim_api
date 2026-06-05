<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tagihan extends Model
{
    protected $table = 'tagihan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'langganan_id', 'nomor', 'periode',
        'jumlah', 'status', 'jatuh_tempo', 'dibayar_pada',
        'midtrans_order_id', 'snap_token',
    ];

    protected $casts = [
        'jatuh_tempo' => 'date',
        'dibayar_pada' => 'datetime',
    ];

    public function gereja()
    {
        return $this->belongsTo(Gereja::class);
    }

    public function langganan()
    {
        return $this->belongsTo(Langganan::class);
    }
}
