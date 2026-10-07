<?php

namespace Dashworthy\DatagridViews\Contracts;

use Dashworthy\DatagridViews\Models\DatagridView;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The access rules for every saved view in one section: who owns a view saved
 * there, whether an actor may have views there at all, and which views an
 * actor may see.
 *
 * The package owns *that* a section has these rules; the host application owns
 * *what* they are, by returning an implementation from
 * {@see DefinesDatagridViewSection::datagridViewAccess()}. Keeping every answer
 * in one object is what stops them drifting apart.
 */
interface GovernsDatagridViewAccess
{
    /**
     * The owning model for a view $actor saves in this section.
     *
     * Never null: a view is always private to exactly one owner.
     */
    public function ownerFor(Authenticatable&Model $actor): Model;

    /**
     * Whether $actor may have views in this section at all: list, save,
     * change or delete them.
     *
     * MUST fail closed: an actor this rule does not recognise gets false.
     */
    public function canUse(Authenticatable&Model $actor): bool;

    /**
     * Narrow the query to the views $actor may see in this section: only the
     * ones it owns.
     *
     * MUST fail closed: an actor with no access, or a query on a connection
     * this section's views do not live on, narrows the query to no rows (e.g.
     * `$query->whereRaw('1 = 0')`), never leaves it unfiltered.
     *
     * @param  Builder<DatagridView>  $query
     */
    public function scopeVisible(Builder $query, Authenticatable&Model $actor): void;
}
