<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Builder;

/*
 | TenantMembersDataGrid is mounted by TestCase::defineRoutes inside
 | `Route::prefix('api/{tenant}')`. Laravel hands route parameters to a
 | controller method positionally, so a hook's `string $view` argument holds
 | the tenant segment there. These fail the moment a hook trusts that argument
 | instead of reading `{view}` from the route bag by name.
 */

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('parameterized', 'grids.tenant-members')->create(['name' => 'Tenant view']);
    $this->views = '/api/acme/grids/tenant-members/views';
    $this->base = "{$this->views}/{$this->view->id}";
});

test('index resolves behind a parameterized prefix', function () {
    $this->actingAs($this->owner)
        ->getJson($this->views)
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->view->id);
});

test('store resolves behind a parameterized prefix', function () {
    $id = $this->actingAs($this->owner)
        ->postJson($this->views, ['name' => 'Created'])
        ->assertCreated()
        ->json('data.id');

    $view = DatagridView::query()->findOrFail($id);

    expect($view->section)->toBe('parameterized')
        ->and($view->grid_key)->toBe('grids.tenant-members');
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

test('a tenant segment that is another view\'s id is never read as the view', function () {
    $other = DatagridView::factory()->ownedBy($this->owner)->forGrid('parameterized', 'grids.tenant-members')->create(['name' => 'Other']);

    $this->actingAs($this->owner)
        ->patchJson("/api/{$other->id}/grids/tenant-members/views/{$this->view->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->view->id);

    expect($other->fresh()->name)->toBe('Other');
});

test('another owner\'s view still 404s behind a parameterized prefix', function () {
    $this->actingAs(User::factory()->create())->patchJson($this->base, ['name' => 'Taken'])->assertNotFound();
});

test('a view from another section 404s behind a parameterized prefix', function () {
    $appView = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.tenant-members')->create();

    $this->actingAs($this->owner)->putJson("{$this->views}/{$appView->id}/default")->assertNotFound();
});

test('resolving the view honours a global scope on the view model', function () {
    DatagridView::addGlobalScope('testing-tenant', function (Builder $query): void {
        $query->whereRaw('1 = 0');
    });

    $this->actingAs($this->owner)->putJson("{$this->base}/default")->assertNotFound();
})->after(function () {
    DatagridView::clearBootedModels();
});
