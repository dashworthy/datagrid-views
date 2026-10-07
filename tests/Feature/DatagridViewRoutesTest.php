<?php

use Dashworthy\DatagridViews\Http\Requests\IndexDatagridViewRequest;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\MembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\TenantMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 | A grid opts into saved views by using InteractsWithDatagridViews, and
 | visualizations' `Route::dataGrid()` mounts a route for each of the trait's
 | hooks. The client (Wayfinder) addresses them by name, so the names, verbs
 | and URIs are part of the package's contract.
 */
test('a grid using the trait gets every view route, named under the grid\'s route name', function (string $name, string $method, string $uri, string $hook) {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe($uri)
        ->and($route->methods())->toContain($method)
        ->and($route->getActionName())->toBe(MembersDataGrid::class.'@'.$hook);
})->with([
    ['grids.members.views', 'GET', 'api/grids/members/views', 'handleViews'],
    ['grids.members.views.store', 'POST', 'api/grids/members/views', 'handleViewStore'],
    ['grids.members.views.update', 'PATCH', 'api/grids/members/views/{view}', 'handleViewUpdate'],
    ['grids.members.views.destroy', 'DELETE', 'api/grids/members/views/{view}', 'handleViewDestroy'],
    ['grids.members.views.default', 'PUT', 'api/grids/members/views/{view}/default', 'handleViewDefault'],
    ['grids.members.views.clear-default', 'DELETE', 'api/grids/members/views/{view}/default', 'handleViewClearDefault'],
]);

test('every grid using the trait gets its own copy of the view routes', function () {
    expect(Route::getRoutes()->getByName('grids.invoices.views')?->uri())->toBe('api/grids/invoices/views')
        ->and(Route::getRoutes()->getByName('grids.admin-members.views.clear-default')?->uri())->toBe('api/grids/admin-members/views/{view}/default')
        ->and(Route::getRoutes()->getByName('grids.tenant-members.views.update')?->getActionName())->toBe(TenantMembersDataGrid::class.'@handleViewUpdate');
});

test('a grid that does not use the trait gets no view routes', function (string $suffix) {
    expect(Route::has('grids.reports.data'))->toBeTrue()
        ->and(Route::has('grids.reports'.$suffix))->toBeFalse();
})->with(['.views', '.views.store', '.views.update', '.views.destroy', '.views.default', '.views.clear-default']);

test('the trait replaces the per-section endpoints: no datagridViewSection macro and no datagrid-views routes', function () {
    $sectionRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_contains($route->uri(), 'datagrid-views'));

    expect(Route::hasMacro('datagridViewSection'))->toBeFalse()
        ->and($sectionRoutes->all())->toBe([]);
});

/*
 | `Route::dataGrid()` calls the hooks as a controller, and visualizations is
 | gaining an interface that declares them: the plain request the container
 | resolves, plus `string $view` on the hooks that name one view, answering
 | with a JsonResponse.
 */
test('each hook has the signature visualizations declares for it', function (string $hook, array $parameters) {
    $method = new ReflectionMethod(MembersDataGrid::class, $hook);

    expect($method->isPublic())->toBeTrue()
        ->and(collect($method->getParameters())->mapWithKeys(fn (ReflectionParameter $parameter): array => [$parameter->getName() => (string) $parameter->getType()])->all())->toBe($parameters)
        ->and((string) $method->getReturnType())->toBe(JsonResponse::class);
})->with([
    ['handleViews', ['request' => Request::class]],
    ['handleViewStore', ['request' => Request::class]],
    ['handleViewUpdate', ['request' => Request::class, 'view' => 'string']],
    ['handleViewDestroy', ['request' => Request::class, 'view' => 'string']],
    ['handleViewDefault', ['request' => Request::class, 'view' => 'string']],
    ['handleViewClearDefault', ['request' => Request::class, 'view' => 'string']],
]);

test('a view request resolved on its own, outside a grid\'s hook, refuses to guess its grid', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => app(IndexDatagridViewRequest::class))
        ->toThrow(LogicException::class, 'is built with forGrid() from a grid\'s view hook');
});
