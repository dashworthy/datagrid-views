<?php

namespace Dashworthy\DatagridViews\Data;

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\Foundry\Data\Data;

/**
 * The view to make its owner's one default for its grid. The owner is read
 * from the view itself, never from the request.
 */
final readonly class SetDefaultDatagridViewData extends Data
{
    public function __construct(
        public DatagridView $view,
    ) {}
}
