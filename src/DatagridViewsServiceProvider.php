<?php

namespace Dashworthy\DatagridViews;

use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Http\Controllers\DatagridViewApiController;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Policies\DatagridViewPolicy;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        $this->registerRouteBindings();

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
            Route::prefix($sectionKey)->name($sectionKey.'.')->group(function () use ($sectionKey): void {
                Route::prefix('datagrid-views')->name('datagrid-views.')->group(function () use ($sectionKey): void {
                    Route::get('/', [DatagridViewApiController::class, 'index'])->name('index')->defaults('section', $sectionKey);
                    Route::post('/', [DatagridViewApiController::class, 'store'])->name('store')->defaults('section', $sectionKey);
                    Route::get('/{datagridView}', [DatagridViewApiController::class, 'show'])->name('show')->defaults('section', $sectionKey);
                    Route::patch('/{datagridView}', [DatagridViewApiController::class, 'update'])->name('update')->defaults('section', $sectionKey);
                    Route::delete('/{datagridView}', [DatagridViewApiController::class, 'destroy'])->name('destroy')->defaults('section', $sectionKey);
                    Route::put('/{datagridView}/default', [DatagridViewApiController::class, 'setDefault'])->name('default')->defaults('section', $sectionKey);
                    Route::delete('/{datagridView}/default', [DatagridViewApiController::class, 'clearDefault'])->name('clear-default')->defaults('section', $sectionKey);
                });
            });
        });
    }

    public function packageBooted(): void
    {
        Gate::policy(DatagridView::class, DatagridViewPolicy::class);
    }

    /**
     * Bind `{datagridView}` by parameter name, resolved inside the acting
     * identity's visible scope for the route's section.
     *
     * By name rather than implicitly: implicit binding reflects on the
     * controller signature, and `Controller::callAction` hands parameters over
     * positionally, so a host nesting the section under its own URL parameter
     * (a `{tenant}` prefix) would shift them. The controller reads the view
     * from the route bag instead.
     *
     * Inside the scope rather than by bare id: the section comes from the
     * route's `section` default and the actor from the authenticated user, so
     * another owner's view, another section's view and an unknown id all 404
     * here, before any policy runs, and the response never tells them apart.
     * Without a section or an Eloquent actor there is no scope to resolve in,
     * so the binding fails closed with a 404. The route's auth middleware runs
     * first (Laravel's middleware priority puts authentication ahead of
     * SubstituteBindings), so a guest gets a 401, not this 404.
     *
     * A null value is handed back untouched: `Router::substituteBindings` walks
     * every parameter by name, and `RouteParameterBinder::replaceDefaults`
     * seeds that bag with every route default, so a host route with a null
     * `datagridView` default would otherwise reach the query.
     */
    protected function registerRouteBindings(): void
    {
        Route::bind('datagridView', function (string|int|null $value, RoutingRoute $route): ?DatagridView {
            if ($value === null) {
                return null;
            }

            $section = $route->parameter('section');
            $actor = Auth::user();

            if (! is_string($section) || ! $actor instanceof Model) {
                throw (new ModelNotFoundException)->setModel(DatagridView::class, [$value]);
            }

            return DatagridView::query()->visibleTo($actor, $section)->findOrFail($value);
        });
    }
}
