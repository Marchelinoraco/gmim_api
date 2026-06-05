<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PembayaranGaji extends Model
{
    use BelongsToGereja, SoftDeletes;

    protected $table = 'pembayaran_gaji';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'pegawai_id', 'periode',
        'tanggal_bayar', 'nominal', 'status',
        'pengeluaran_id', 'input_by', 'keterangan',
    ];

    protected $casts = [
        'nominal'      => 'integer',
        'tanggal_bayar'=> 'date:Y-m-d',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
