<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;

class NamaPersembahan extends Model
{
    use BelongsToGereja;
    protected $table = 'nama_persembahan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'gereja_id', 'kategori_persembahan_id', 'nama'];
}
