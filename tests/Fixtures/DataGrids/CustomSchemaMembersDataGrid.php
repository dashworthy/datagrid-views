<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;

/**
 * Keeps saved views and overrides getSchemaBody() with its own key, without
 * adding the views flag, so the suite can prove the flag survives a grid
 * customising its schema body.
 */
final class CustomSchemaMembersDataGrid extends FixtureDataGrid implements HasDatagridViews
{
    use InteractsWithDatagridViews;

    public function getDatagridViewSection(): DefinesDatagridViewSection
    {
        return TestSection::App;
    }

    protected function getSchemaBody(): array
    {
        return [...parent::getSchemaBody(), 'density' => 'compact'];
    }
}
