<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Global Scope tenant isolation.
 * Setiap model yang memakai trait ini otomatis memfilter berdasarkan gereja aktif
 * (di-set oleh middleware SetCurrentGereja dari route param {gereja}).
 *
 * Dipasang setelah middleware EnsureUserBelongsToGereja → tiga lapisan:
 *   1. EnsureUserBelongsToGereja: user.gereja_id === route gereja
 *   2. SetCurrentGereja: bind 'currentGerejaId' ke app container
 *   3. Global Scope ini: otomatis WHERE gereja_id pada semua query
 */
trait BelongsToGereja
{
    protected static function bootBelongsToGereja(): void
    {
        static::addGlobalScope('gereja', function (Builder $builder): void {
            $gerejaId = app()->bound('currentGerejaId') ? app('currentGerejaId') : null;
            if ($gerejaId) {
                $builder->where(
                    $builder->getModel()->getTable().'.gereja_id',
                    $gerejaId
                );
            }
        });

        static::creating(function (self $model): void {
            if (empty($model->gereja_id) && app()->bound('currentGerejaId')) {
                $model->gereja_id = app('currentGerejaId');
            }
        });
    }
}
