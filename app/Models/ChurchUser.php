<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGereja;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class ChurchUser extends Model
{
    use BelongsToGereja, HasApiTokens;

    protected $table = 'church_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['password'];

    protected $fillable = [
        'id', 'gereja_id', 'nama', 'email', 'role', 'username', 'password',
        'status', 'login_terakhir', 'sso_user_id',
    ];

    protected $casts = [
        'login_terakhir' => 'datetime',
    ];

    public function gereja()
    {
        return $this->belongsTo(Gereja::class, 'gereja_id');
    }
}
