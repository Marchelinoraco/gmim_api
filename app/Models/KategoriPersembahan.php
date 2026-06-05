<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;

class KategoriPersembahan extends Model
{
    use BelongsToGereja;
    protected $table = 'kategori_persembahan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'gereja_id', 'nama'];

    public function pemasukan()
    {
        return $this->hasMany(Pemasukan::class, 'kategori_persembahan_id');
    }
}
