<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table    = 'audit_log';
    protected $keyType  = 'string';
    public $incrementing = false;
    public $timestamps   = false;

    protected $fillable = [
        'id', 'gereja_id', 'actor_id', 'actor_email',
        'action', 'entity_type', 'entity_id', 'meta', 'ip',
    ];

    protected $casts = ['meta' => 'array'];

    public static function record(
        string $action,
        ?string $gerejaId   = null,
        ?string $entityType = null,
        ?string $entityId   = null,
        array   $meta       = [],
        ?string $actorId    = null,
        ?string $actorEmail = null,
        ?string $ip         = null,
    ): void {
        static::create([
            'id'          => 'audit-' . Str::ulid(),
            'gereja_id'   => $gerejaId,
            'actor_id'    => $actorId,
            'actor_email' => $actorEmail,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'meta'        => $meta ?: null,
            'ip'          => $ip,
            'created_at'  => now(),
        ]);
    }
}
