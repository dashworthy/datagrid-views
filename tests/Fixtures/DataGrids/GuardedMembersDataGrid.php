<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;

/**
 * Keeps saved views in the app section, behind the grid's own permission
 * (the visualizations package gates a grid's data and schema on the ability a
 * `getPermissionName()` names), so the suite can prove its views sit behind
 * the same gate.
 */
final class GuardedMembersDataGrid extends FixtureDataGrid implements HasDatagridViews
{
    use InteractsWithDatagridViews;

    public const string PERMISSION = 'see-guarded-members';

    public function getDatagridViewSection(): DefinesDatagridViewSection
    {
        return TestSection::App;
    }

    public function getPermissionName(): string
    {
        return self::PERMISSION;
    }
}
