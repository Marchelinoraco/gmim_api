<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;

class MutasiKas extends Model
{
    use BelongsToGereja;

    protected $table     = 'mutasi_kas';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'tanggal', 'pos_asal_id', 'pos_tujuan_id',
        'jumlah', 'biaya_admin', 'keterangan', 'dicatat_oleh',
        'reverses', 'reversed_by', 'alasan_koreksi',
    ];

    protected $casts = [
        'tanggal'     => 'date:Y-m-d',
        'jumlah'      => 'integer',
        'biaya_admin' => 'integer',
    ];

    public function posAsal()
    {
        return $this->belongsTo(PosKas::class, 'pos_asal_id');
    }

    public function posTujuan()
    {
        return $this->belongsTo(PosKas::class, 'pos_tujuan_id');
    }
}
