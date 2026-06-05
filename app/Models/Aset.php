<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Aset extends Model
{
    use BelongsToGereja, SoftDeletes;

    protected $table = 'aset';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'kode', 'nama', 'kategori',
        'tanggal_perolehan', 'nilai_perolehan',
        'lokasi', 'kondisi', 'bukti_gambar', 'keterangan',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date:Y-m-d',
        'nilai_perolehan'   => 'integer',
    ];
}
