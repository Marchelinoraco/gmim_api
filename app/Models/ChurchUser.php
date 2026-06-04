<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class ChurchUser extends Model
{
    use HasApiTokens;

    protected $table = 'church_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['password'];

    protected $fillable = ['id', 'gereja_id', 'nama', 'role', 'username', 'password'];

    public function gereja()
    {
        return $this->belongsTo(Gereja::class, 'gereja_id');
    }
}
