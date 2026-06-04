<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gereja extends Model
{
    protected $table = 'gereja';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'nama', 'alamat'];
}
