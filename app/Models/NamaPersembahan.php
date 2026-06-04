<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NamaPersembahan extends Model
{
    protected $table = 'nama_persembahan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'gereja_id', 'kategori_persembahan_id', 'nama'];
}
