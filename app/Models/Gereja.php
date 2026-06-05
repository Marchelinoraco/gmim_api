<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gereja extends Model
{
    protected $table = 'gereja';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'nama', 'slug', 'subdomain', 'alamat',
        'nama_pendeta', 'telepon', 'email',
        'status_langganan', 'bergabung_pada', 'multi_pos_kas',
    ];

    protected $casts = [
        'multi_pos_kas' => 'boolean',
    ];

    public function langganan()
    {
        return $this->hasOne(Langganan::class);
    }

    public function posKas()
    {
        return $this->hasMany(PosKas::class);
    }

    public function pengguna()
    {
        return $this->hasMany(ChurchUser::class);
    }
}
