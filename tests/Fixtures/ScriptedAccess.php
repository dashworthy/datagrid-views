<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An owner-only access rule whose canUse() answer each test sets, so the
 * suite can prove every endpoint refuses an actor the section shuts out even
 * when that actor still owns views there. TestCase resets it to true before
 * every test.
 */
final class ScriptedAccess implements GovernsDatagridViewAccess
{
    public static bool $canUse = true;

    public function ownerFor(Authenticatable&Model $actor): Model
    {
        return $actor;
    }

    public function canUse(Authenticatable&Model $actor): bool
    {
        return self::$canUse;
    }

    public function scopeVisible(Builder $query, Authenticatable&Model $actor): void
    {
        $query->where('owner_type', $actor->getMorphClass())
            ->where('owner_id', $actor->getKey());
    }
}
