<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class PlatformAdmin extends Model
{
    use HasApiTokens;

    protected $table      = 'platform_admins';
    protected $keyType    = 'string';
    public $incrementing  = false;
    protected $hidden     = ['password'];

    protected $fillable = [
        'id', 'nama', 'email', 'password', 'role', 'is_active', 'login_terakhir',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'login_terakhir' => 'datetime',
    ];
}
