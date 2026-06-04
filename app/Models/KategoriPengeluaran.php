<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriPengeluaran extends Model
{
    protected $table = 'kategori_pengeluaran';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'gereja_id', 'nama'];
}
