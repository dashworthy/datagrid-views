<?php

namespace Dashworthy\DatagridViews\Tests;

use Dashworthy\DatagridViews\DatagridViewsServiceProvider;
use Dashworthy\DatagridViews\Tests\Fixtures\ScriptedAccess;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Dashworthy\Visualizations\VisualizationsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\SubstituteBindings;
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
     * Mount the sections the way a host application does, each inside the
     * host's auth group.
     */
    protected function defineRoutes($router)
    {
        Route::middleware(['auth', SubstituteBindings::class])->prefix('api')->group(function () {
            Route::datagridViewSection(TestSection::Admin);
            Route::datagridViewSection(TestSection::App);
            Route::datagridViewSection(TestSection::Scripted);
        });

        /*
         | A host may nest a section under a group carrying its own URL
         | parameter (a multi-tenant app's `{tenant}` prefix). That shifts every
         | positional controller argument, so the package must read its model
         | from the route bag. Exercised by ParameterizedPrefixSectionTest.
         */
        Route::middleware(['auth', SubstituteBindings::class])->prefix('api/{tenant}')->group(function () {
            Route::datagridViewSection(TestSection::Parameterized);
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
