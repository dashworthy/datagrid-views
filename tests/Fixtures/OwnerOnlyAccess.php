<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The access rule the package's own suite runs on: the acting identity owns
 * the views it saves, may use views in any section, and sees only what it
 * owns. Matching on both morph columns keeps two identity types that share a
 * key apart.
 */
final class OwnerOnlyAccess implements GovernsDatagridViewAccess
{
    public function ownerFor(Authenticatable&Model $actor): Model
    {
        return $actor;
    }

    public function canUse(Authenticatable&Model $actor): bool
    {
        return true;
    }

    public function scopeVisible(Builder $query, Authenticatable&Model $actor): void
    {
        $query->where('owner_type', $actor->getMorphClass())
            ->where('owner_id', $actor->getKey());
    }
}
