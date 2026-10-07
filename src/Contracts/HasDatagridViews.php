<?php

namespace Dashworthy\DatagridViews\Contracts;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;

/**
 * A data grid that keeps saved views. Implemented by a visualizations
 * DataGrid alongside {@see InteractsWithDatagridViews}, which supplies the
 * view routes' hooks; the grid only declares where its views live.
 */
interface HasDatagridViews
{
    /**
     * The section this grid's views are saved in and whose access rules govern
     * them. It must be listed in `config('datagrid-views.sections')`, and no
     * other listed enum may claim its key.
     */
    public function getDatagridViewSection(): DefinesDatagridViewSection;
}
