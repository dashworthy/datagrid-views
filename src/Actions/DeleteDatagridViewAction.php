<?php

namespace Dashworthy\DatagridViews\Actions;

use Dashworthy\DatagridViews\Data\DeleteDatagridViewData;
use Dashworthy\Foundry\Actions\Action;

/**
 * Deletes a view for good. Deleting the default leaves the grid with no
 * default, so the page's own setup opens first again; no other view is
 * promoted in its place.
 *
 * @extends Action<DeleteDatagridViewData, void>
 */
class DeleteDatagridViewAction extends Action
{
    protected function handle(DeleteDatagridViewData $data): void
    {
        $data->view->delete();
    }
}
