<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GerejaMidtrans extends Model
{
    protected $table = 'gereja_midtrans';

    protected $primaryKey = 'gereja_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'gereja_id',
        'server_key',
        'client_key',
        'merchant_id',
        'is_production',
        'is_active',
    ];

    protected $casts = [
        'is_production' => 'boolean',
        'is_active'     => 'boolean',
        // server_key dienkripsi otomatis saat disimpan, didekripsi saat dibaca
        'server_key'    => 'encrypted',
    ];

    // server_key tidak pernah keluar via API — hidden dari serialisasi JSON
    protected $hidden = ['server_key'];

    public function gereja()
    {
        return $this->belongsTo(Gereja::class, 'gereja_id');
    }
}
