<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;

/**
 * Keeps saved views: the app section's members grid, mounted at `api/grids/members` (visualization key `grids.members`).
 */
final class MembersDataGrid extends FixtureDataGrid implements HasDatagridViews
{
    use InteractsWithDatagridViews;

    public function getDatagridViewSection(): DefinesDatagridViewSection
    {
        return TestSection::App;
    }
}
