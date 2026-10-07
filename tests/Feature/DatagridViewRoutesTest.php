<?php

use Illuminate\Support\Facades\Route;

/*
 | The client (Wayfinder) addresses these endpoints by name, so the names and
 | URIs are part of the package's contract.
 */
test('each mounted section names its endpoints under its key', function (string $name, string $method, string $uri) {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe($uri)
        ->and($route->methods())->toContain($method)
        ->and($route->defaults['section'] ?? null)->toBe('app');
})->with([
    ['app.datagrid-views.index', 'GET', 'api/app/datagrid-views'],
    ['app.datagrid-views.store', 'POST', 'api/app/datagrid-views'],
    ['app.datagrid-views.show', 'GET', 'api/app/datagrid-views/{datagridView}'],
    ['app.datagrid-views.update', 'PATCH', 'api/app/datagrid-views/{datagridView}'],
    ['app.datagrid-views.destroy', 'DELETE', 'api/app/datagrid-views/{datagridView}'],
    ['app.datagrid-views.default', 'PUT', 'api/app/datagrid-views/{datagridView}/default'],
    ['app.datagrid-views.clear-default', 'DELETE', 'api/app/datagrid-views/{datagridView}/default'],
]);

test('a second section gets its own copy of every endpoint', function () {
    expect(Route::getRoutes()->getByName('admin.datagrid-views.index')?->defaults['section'])->toBe('admin')
        ->and(Route::getRoutes()->getByName('admin.datagrid-views.clear-default')?->uri())->toBe('api/admin/datagrid-views/{datagridView}/default');
});
