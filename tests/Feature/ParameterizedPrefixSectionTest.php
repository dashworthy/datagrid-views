<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
 | The `parameterized` section is mounted by TestCase::defineRoutes inside
 | `Route::prefix('api/{tenant}')`. That leading parameter shifts every
 | positional controller argument by one, so these fail with a 500 the moment
 | an action declares a typed DatagridView parameter instead of reading the
 | view from the route bag.
 */

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('parameterized', 'members')->create(['name' => 'Tenant view']);
    $this->base = "/api/acme/parameterized/datagrid-views/{$this->view->id}";
});

test('index resolves behind a parameterized prefix', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/acme/parameterized/datagrid-views?grid=members')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->view->id);
});

test('store resolves behind a parameterized prefix', function () {
    $id = $this->actingAs($this->owner)
        ->postJson('/api/acme/parameterized/datagrid-views', ['grid_key' => 'members', 'name' => 'Created'])
        ->assertCreated()
        ->json('data.id');

    expect(DatagridView::query()->findOrFail($id)->section)->toBe('parameterized');
});

test('show resolves the view behind a parameterized prefix', function () {
    $this->actingAs($this->owner)->getJson($this->base)->assertOk()->assertJsonPath('data.name', 'Tenant view');
});

test('update resolves the view behind a parameterized prefix', function () {
    $this->actingAs($this->owner)->patchJson($this->base, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
});

test('set and clear default resolve the view behind a parameterized prefix', function () {
    $this->actingAs($this->owner)->putJson("{$this->base}/default")->assertOk()->assertJsonPath('data.is_default', true);
    $this->actingAs($this->owner)->deleteJson("{$this->base}/default")->assertOk()->assertJsonPath('data.is_default', false);
});

test('destroy resolves the view behind a parameterized prefix', function () {
    $this->actingAs($this->owner)->deleteJson($this->base)->assertNoContent();

    expect(DatagridView::query()->whereKey($this->view->id)->exists())->toBeFalse();
});

test('another owner\'s view still 404s behind a parameterized prefix', function () {
    $this->actingAs(User::factory()->create())->getJson($this->base)->assertNotFound();
});

test('a view from another section 404s behind a parameterized prefix', function () {
    $appView = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();

    $this->actingAs($this->owner)->getJson("/api/acme/parameterized/datagrid-views/{$appView->id}")->assertNotFound();
});

test('route binding honours a global scope on the view model', function () {
    DatagridView::addGlobalScope('testing-tenant', function (Builder $query): void {
        $query->whereRaw('1 = 0');
    });

    $this->actingAs($this->owner)->getJson($this->base)->assertNotFound();
})->after(function () {
    DatagridView::clearBootedModels();
});

/*
 | Router::substituteBindings walks every parameter of the matched route by
 | name, and RouteParameterBinder::replaceDefaults seeds that bag with every
 | route default, including ones the URI never declares. A host route with a
 | null `datagridView` default must not reach the binder's query.
 */
test('a null route default of the same name does not reach the binder', function () {
    Route::middleware(SubstituteBindings::class)
        ->get('/host-app/thing/{other}', fn (string $other): array => ['other' => $other])
        ->defaults('datagridView', null);

    $this->getJson('/host-app/thing/xyz')->assertOk()->assertJsonPath('other', 'xyz');
});
