<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;

class KategoriPengeluaran extends Model
{
    use BelongsToGereja;
    protected $table = 'kategori_pengeluaran';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'gereja_id', 'nama'];

    public function pengeluaran()
    {
        return $this->hasMany(Pengeluaran::class, 'kategori_pengeluaran_id');
    }
}
