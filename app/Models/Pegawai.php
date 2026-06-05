<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends Model
{
    use BelongsToGereja, SoftDeletes;

    protected $table = 'pegawai';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'nama', 'jabatan', 'tipe',
        'nominal_default', 'no_rekening', 'bank', 'status', 'keterangan',
    ];

    protected $casts = [
        'nominal_default' => 'integer',
    ];

    public function pembayaran()
    {
        return $this->hasMany(PembayaranGaji::class, 'pegawai_id');
    }
}
