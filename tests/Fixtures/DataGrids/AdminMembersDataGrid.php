<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;

/**
 * Keeps saved views: the admin section's grid.
 */
final class AdminMembersDataGrid extends FixtureDataGrid implements HasDatagridViews
{
    use InteractsWithDatagridViews;

    public function getDatagridViewSection(): DefinesDatagridViewSection
    {
        return TestSection::Admin;
    }
}
