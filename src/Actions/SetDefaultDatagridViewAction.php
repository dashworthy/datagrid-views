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
 * anything changes, so two concurrent calls for the same owner and grid run
 * one after the other rather than each clearing the other's flag and both
 * setting their own. Clearing the previous default and setting the new one
 * share the transaction {@see Action} owns: if either fails, both roll back
 * and the previous default stands. Other grids, sections and owners are never
 * touched.
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
