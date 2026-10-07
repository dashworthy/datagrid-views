<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures\DataGrids;

/**
 * A grid that does not keep saved views: it neither implements HasDatagridViews
 * nor uses the trait, so `Route::dataGrid()` mounts no view routes for it and
 * its schema carries no views flag.
 */
final class ReportsDataGrid extends FixtureDataGrid {}
