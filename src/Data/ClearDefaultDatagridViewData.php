<?php

namespace Dashworthy\DatagridViews\Data;

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\Foundry\Data\Data;

/**
 * The view to stop being its owner's default, leaving the grid with none:
 * the page's own setup opens first again.
 */
final readonly class ClearDefaultDatagridViewData extends Data
{
    public function __construct(
        public DatagridView $view,
    ) {}
}
