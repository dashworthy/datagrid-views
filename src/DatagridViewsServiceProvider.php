<?php

namespace Dashworthy\DatagridViews;

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Policies\DatagridViewPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Registers the config, the migration and the view policy. The package mounts
 * no routes of its own: a grid opts into saved views with
 * {@see Concerns\InteractsWithDatagridViews}, and visualizations'
 * `Route::dataGrid()` mounts that grid's view routes from the trait's hooks.
 */
class DatagridViewsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('datagrid-views')
            ->hasConfigFile()
            ->hasMigration('create_datagrid_views_table');
    }

    public function packageBooted(): void
    {
        Gate::policy(DatagridView::class, DatagridViewPolicy::class);
    }
}
