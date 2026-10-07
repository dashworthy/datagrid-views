<?php

namespace Dashworthy\DatagridViews\Data;

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\Foundry\Data\Data;

/**
 * The view to delete.
 */
final readonly class DeleteDatagridViewData extends Data
{
    public function __construct(
        public DatagridView $view,
    ) {}
}
