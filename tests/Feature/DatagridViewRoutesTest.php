<?php

use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Http\Requests\IndexDatagridViewRequest;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\MembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\ReportsDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\TenantMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Dashworthy\Visualizations\Contracts\HandlesDataGridViews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

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
 | A grid using the trait implements visualizations' HandlesDataGridViews,
 | which is what makes `Route::dataGrid()` mount all six view routes. Each hook
 | takes only the request (the view id is read by name from the route), and
 | answers with a JsonResponse, or a Symfony Response where the interface
 | allows a bodiless answer.
 */
test('a grid using the trait is a HandlesDataGridViews, and a grid without it is not', function () {
    expect(new MembersDataGrid)->toBeInstanceOf(HandlesDataGridViews::class)
        ->and(new MembersDataGrid)->toBeInstanceOf(HasDatagridViews::class)
        ->and(new ReportsDataGrid)->not->toBeInstanceOf(HandlesDataGridViews::class);
});

test('each hook has exactly the signature HandlesDataGridViews declares', function (string $hook, string $returnType) {
    $method = new ReflectionMethod(MembersDataGrid::class, $hook);
    $declared = new ReflectionMethod(HandlesDataGridViews::class, $hook);

    expect($method->isPublic())->toBeTrue()
        ->and(collect($method->getParameters())->mapWithKeys(fn (ReflectionParameter $parameter): array => [$parameter->getName() => (string) $parameter->getType()])->all())->toBe(['request' => Request::class])
        ->and((string) $method->getReturnType())->toBe($returnType)
        ->and((string) $declared->getReturnType())->toBe($returnType);
})->with([
    ['handleViews', JsonResponse::class],
    ['handleViewStore', JsonResponse::class],
    ['handleViewUpdate', JsonResponse::class],
    ['handleViewDestroy', Response::class],
    ['handleViewDefault', Response::class],
    ['handleViewClearDefault', Response::class],
]);

test('all six view routes mount behind a host prefix that carries its own parameter', function (string $suffix, string $method, string $uri) {
    $route = Route::getRoutes()->getByName('grids.tenant-members'.$suffix);

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe($uri)
        ->and($route->methods())->toContain($method);
})->with([
    ['.views', 'GET', 'api/{tenant}/grids/tenant-members/views'],
    ['.views.store', 'POST', 'api/{tenant}/grids/tenant-members/views'],
    ['.views.update', 'PATCH', 'api/{tenant}/grids/tenant-members/views/{view}'],
    ['.views.destroy', 'DELETE', 'api/{tenant}/grids/tenant-members/views/{view}'],
    ['.views.default', 'PUT', 'api/{tenant}/grids/tenant-members/views/{view}/default'],
    ['.views.clear-default', 'DELETE', 'api/{tenant}/grids/tenant-members/views/{view}/default'],
]);

test('a view request resolved on its own, outside a grid\'s hook, refuses to guess its grid', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => app(IndexDatagridViewRequest::class))
        ->toThrow(LogicException::class, 'is built with forGrid() from a grid\'s view hook');
});
