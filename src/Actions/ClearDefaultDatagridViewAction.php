<?php

namespace Dashworthy\DatagridViews\Actions;

use Dashworthy\DatagridViews\Data\ClearDefaultDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\Foundry\Actions\Action;

/**
 * Stops a view being its owner's default, leaving the grid with none: the
 * page's own setup opens first again. Clearing a view that is not the default
 * changes nothing.
 *
 * @extends Action<ClearDefaultDatagridViewData, DatagridView>
 */
class ClearDefaultDatagridViewAction extends Action
{
    protected function handle(ClearDefaultDatagridViewData $data): DatagridView
    {
        $data->view->is_default = false;
        $data->view->save();

        return $data->view;
    }
}
