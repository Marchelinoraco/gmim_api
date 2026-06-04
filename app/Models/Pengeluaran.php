<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    protected $table = 'pengeluaran';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'gereja_id', 'tanggal', 'kategori_pengeluaran_id', 'jumlah', 'keterangan'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'jumlah' => 'integer',
    ];
}
