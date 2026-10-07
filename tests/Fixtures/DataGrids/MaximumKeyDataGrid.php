<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;

/**
 * Keeps saved views under a long route prefix, so its visualization key is
 * exactly as long as the `grid_key` column holds. Mounted by the test that uses it.
 */
final class MaximumKeyDataGrid extends FixtureDataGrid implements HasDatagridViews
{
    use InteractsWithDatagridViews;

    public function getDatagridViewSection(): DefinesDatagridViewSection
    {
        return TestSection::App;
    }

    public function getRoutePrefix(): string
    {
        return str_repeat('g', 115);
    }
}
