<?php

namespace Dashworthy\DatagridViews\Contracts;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\Visualizations\Contracts\HandlesDataGridViews;

/**
 * A data grid that keeps saved views. Implemented by a visualizations
 * DataGrid alongside {@see InteractsWithDatagridViews}, which supplies the
 * six view hooks {@see HandlesDataGridViews} declares; the grid only declares
 * where its views live. Being a HandlesDataGridViews is what makes
 * `Route::dataGrid()` mount the grid's view routes.
 */
interface HasDatagridViews extends HandlesDataGridViews
{
    /**
     * The section this grid's views are saved in and whose access rules govern
     * them. It must be listed in `config('datagrid-views.sections')`, and no
     * other listed enum may claim its key.
     */
    public function getDatagridViewSection(): DefinesDatagridViewSection;
}
