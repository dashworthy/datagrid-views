<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

use Dashworthy\Visualizations\DataGrids\Abstracts\Column;
use Dashworthy\Visualizations\DataGrids\Abstracts\DataGrid;
use Dashworthy\Visualizations\DataGrids\Columns\Text;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The columns and query every fixture grid shares. A fixture grid differs only
 * in its class name (which sets its route name, path and visualization key)
 * and in whether, and for which section, it keeps saved views.
 */
abstract class FixtureDataGrid extends DataGrid
{
    public function getColumns(): Collection
    {
        return collect($this->columnList());
    }

    public function getQuery(): Builder
    {
        return DB::table('users');
    }

    /**
     * @return list<Column>
     */
    private function columnList(): array
    {
        return [
            Text::make('users.name', 'Name'),
            Text::make('users.email', 'Email'),
        ];
    }
}
