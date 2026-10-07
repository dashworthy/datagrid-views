<?php

namespace Dashworthy\DatagridViews;

use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DatagridViewsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('datagrid-views')
            ->hasConfigFile()
            ->hasMigration('create_datagrid_views_table');
    }

    public function packageRegistered(): void
    {
        Route::macro('datagridViewSection', function (DefinesDatagridViewSection $section): void {
            $sectionKey = $section->getSectionKey();

            // Fail while routes load, not on the first request. A section
            // missing from config('datagrid-views.sections') has no access
            // rules to read. Two configured enums sharing a key would make
            // DatagridViewSections::find() resolve to whichever is listed
            // first, putting one section's views under the other's rules.
            $sections = app(DatagridViewSections::class);
            $claiming = $sections->claiming($sectionKey);

            if (count($claiming) > 1) {
                throw new InvalidArgumentException(
                    "Datagrid view section key [{$sectionKey}] is registered by more than one enum in config('datagrid-views.sections')."
                );
            }

            if (($claiming[0] ?? null) !== $section) {
                throw $sections->notListed($sectionKey);
            }

            // Every endpoint of the section lives under its key, in URL and
            // route name alike, and carries ->defaults('section', $sectionKey).
            Route::prefix($sectionKey)->name($sectionKey.'.')->group(function (): void {});
        });
    }
}
