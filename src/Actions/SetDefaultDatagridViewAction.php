<?php

namespace Dashworthy\DatagridViews\Actions;

use Dashworthy\DatagridViews\Data\SetDefaultDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\Foundry\Actions\Action;

/**
 * Makes a view its owner's default for its grid, holding the invariant of at
 * most one default per (section, grid, owner).
 *
 * The owner's views of that grid are locked (`SELECT ... FOR UPDATE`) before
 * anything changes. Clearing the previous default and setting the new one
 * share the transaction {@see Action} owns: if either fails, both roll back
 * and the previous default stands. Other grids, sections and owners are never
 * touched.
 *
 * What the lock does and does not guarantee:
 * - On MySQL/MariaDB (InnoDB), two concurrent set-default calls for the same
 *   owner and grid queue on the lock, at any isolation level. The locking read
 *   also waits on a row a concurrent create has inserted but not committed,
 *   which is why {@see CreateDatagridViewAction} inserts before calling this.
 * - Two concurrent creates-with-default for the same owner and grid can
 *   instead deadlock, each waiting on the other's new row. InnoDB rolls one
 *   back (a DeadlockException, a 500 for that request); the invariant holds.
 *   Foundry's Action has no retry option, so none is attempted here.
 * - SQLite serializes writers, so neither case arises there.
 * - PostgreSQL's FOR UPDATE does not wait on uncommitted inserts, so two
 *   concurrent creates-with-default could both end up default there.
 *
 * @extends Action<SetDefaultDatagridViewData, DatagridView>
 */
class SetDefaultDatagridViewAction extends Action
{
    protected function handle(SetDefaultDatagridViewData $data): DatagridView
    {
        $view = $data->view;

        DatagridView::query()->siblingsOf($view)->lockForUpdate()->pluck('id');

        DatagridView::query()
            ->siblingsOf($view)
            ->whereKeyNot($view->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $view->is_default = true;
        $view->save();

        return $view;
    }
}
