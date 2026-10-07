<?php

namespace Dashworthy\DatagridViews\Tests;

use Dashworthy\DatagridViews\DatagridViewsServiceProvider;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\AdminMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\CustomSchemaMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\GuardedMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\InvoicesDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\MembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\ReportsDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\ScriptedMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\TenantMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\ScriptedAccess;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Dashworthy\Visualizations\VisualizationsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // ScriptedAccess's static is written by several test files; reset it
        // so it never leaks between tests whatever the execution order.
        ScriptedAccess::$canUse = true;
    }

    protected function getPackageProviders($app)
    {
        return [
            DatagridViewsServiceProvider::class,
            VisualizationsServiceProvider::class,
        ];
    }

    /**
     * Mount the fixture grids the way a host application does, each inside
     * the host's auth group. `Route::dataGrid()` adds a grid's view routes
     * only when the grid uses InteractsWithDatagridViews.
     */
    protected function defineRoutes($router)
    {
        Route::middleware('auth')->prefix('api')->group(function () {
            Route::dataGrid(MembersDataGrid::class);
            Route::dataGrid(InvoicesDataGrid::class);
            Route::dataGrid(AdminMembersDataGrid::class);
            Route::dataGrid(ScriptedMembersDataGrid::class);
            Route::dataGrid(GuardedMembersDataGrid::class);
            Route::dataGrid(CustomSchemaMembersDataGrid::class);
            Route::dataGrid(ReportsDataGrid::class);
        });

        /*
         | A host may nest a grid under a group carrying its own URL parameter
         | (a multi-tenant app's `{tenant}` prefix). Laravel hands route
         | parameters to a controller method positionally, so the views code
         | must read `{view}` from the route bag. Exercised by
         | ParameterizedPrefixTest.
         */
        Route::middleware('auth')->prefix('api/{tenant}')->group(function () {
            Route::dataGrid(TenantMembersDataGrid::class);
        });
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('datagrid-views.sections', [TestSection::class]);

        config()->set('auth.providers.users.model', User::class);

        foreach (['users', 'admins'] as $identityTable) {
            Schema::create($identityTable, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        (include __DIR__.'/../database/migrations/create_datagrid_views_table.php.stub')->up();
    }
}
