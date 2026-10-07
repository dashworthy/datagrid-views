<?php

namespace Dashworthy\DatagridViews\Tests;

use Dashworthy\DatagridViews\DatagridViewsServiceProvider;
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

    protected function getPackageProviders($app)
    {
        return [
            DatagridViewsServiceProvider::class,
            VisualizationsServiceProvider::class,
        ];
    }

    /**
     * Mount both sections the way a host application does: the admin one on
     * its own, the app one beside it, each inside the host's auth group.
     */
    protected function defineRoutes($router)
    {
        Route::middleware(['auth', SubstituteBindings::class])->prefix('api')->group(function () {
            Route::datagridViewSection(TestSection::Admin);
            Route::datagridViewSection(TestSection::App);
        });
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('datagrid-views.sections', [TestSection::class]);

        config()->set('auth.providers.users.model', User::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        (include __DIR__.'/../database/migrations/create_datagrid_views_table.php.stub')->up();
    }
}
