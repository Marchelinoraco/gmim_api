<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paket extends Model
{
    protected $table = 'paket';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'nama', 'harga_bulanan', 'harga_tahunan', 'batas', 'is_active',
    ];

    protected $casts = [
        'batas'      => 'array',
        'is_active'  => 'boolean',
    ];

    public function langganan()
    {
        return $this->hasMany(Langganan::class);
    }
}
