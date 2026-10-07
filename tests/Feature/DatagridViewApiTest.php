<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\Admin;
use Dashworthy\DatagridViews\Tests\Fixtures\CollidingSection;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\GuardedMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\MembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\ScriptedAccess;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 | The members grid (MembersDataGrid) keeps its views in the app section under
 | its visualization key, `grids.members`, and is mounted at
 | `api/grids/members`. Neither the section nor the grid key is ever sent by
 | the client: both come from the grid handling the request.
 */

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->withState()->create(['name' => 'Active members']);
    $this->views = '/api/grids/members/views';
    $this->base = "{$this->views}/{$this->view->id}";
});

// --- index ---

test('index lists only the actor\'s own views of the grid handling the request', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.invoices')->create(['name' => 'Other grid']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'grids.members')->create(['name' => 'Other section']);
    DatagridView::factory()->ownedBy($this->otherUser)->forGrid('app', 'grids.members')->create(['name' => 'Other owner']);

    $this->actingAs($this->owner)
        ->getJson($this->views)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->view->id)
        ->assertJsonPath('data.0.name', 'Active members');
});

test('index ignores a grid named in the query string', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.invoices')->create(['name' => 'Other grid']);

    $this->actingAs($this->owner)
        ->getJson("{$this->views}?grid=grids.invoices&grid_key=grids.invoices")
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Active members']);
});

test('index never lists a view owned by an identity of another type sharing the actor\'s key', function () {
    $admin = Admin::factory()->create();
    $user = User::query()->findOrFail($admin->getKey());

    DatagridView::factory()->ownedBy($admin)->forGrid('app', 'grids.invoices')->create(['name' => 'Admin view']);

    $this->actingAs($user)
        ->getJson('/api/grids/invoices/views')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('index lists the default view first, then the rest by name ignoring case', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create(['name' => 'beta']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->asDefault()->create(['name' => 'Zulu']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create(['name' => 'Alpha']);

    $this->actingAs($this->owner)
        ->getJson($this->views)
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Zulu', 'Active members', 'Alpha', 'beta']);
});

test('index answers with exactly the resource fields', function () {
    $this->actingAs($this->owner)
        ->getJson($this->views)
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'grid_key', 'name', 'filter_sets', 'sorts', 'visibility', 'page_size', 'is_default', 'updated_at']]])
        ->assertJsonPath('data.0.grid_key', 'grids.members')
        ->assertJsonPath('data.0.page_size', 25)
        ->assertJsonPath('data.0.is_default', false)
        ->assertJsonMissingPath('data.0.owner_id')
        ->assertJsonMissingPath('data.0.owner_type')
        ->assertJsonMissingPath('data.0.section');
});

// --- store ---

test('store creates a view owned by the actor in the grid\'s section, under the grid\'s key, and answers 201', function () {
    $response = $this->actingAs($this->owner)
        ->postJson($this->views, ['name' => 'Recent', ...gridState(), 'page_size' => 50])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Recent')
        ->assertJsonPath('data.grid_key', 'grids.members')
        ->assertJsonPath('data.page_size', 50)
        ->assertJsonPath('data.is_default', false);

    $view = DatagridView::query()->findOrFail($response->json('data.id'));

    expect($view->section)->toBe('app')
        ->and($view->grid_key)->toBe((new MembersDataGrid)->getVisualizationKey())
        ->and($view->owner_type)->toBe($this->owner->getMorphClass())
        ->and($view->owner_id)->toBe($this->owner->getKey());
});

test('store needs only a name', function () {
    $this->actingAs($this->owner)
        ->postJson($this->views, ['name' => 'Bare'])
        ->assertCreated()
        ->assertJsonPath('data.filter_sets', null)
        ->assertJsonPath('data.sorts', null)
        ->assertJsonPath('data.visibility', null)
        ->assertJsonPath('data.page_size', null)
        ->assertJsonPath('data.is_default', false);
});

test('store ignores a grid, owner or section named in the payload', function () {
    $response = $this->actingAs($this->owner)
        ->postJson($this->views, [
            'name' => 'Sneaky',
            'grid_key' => 'grids.invoices',
            'section' => 'admin',
            'owner_type' => $this->otherUser->getMorphClass(),
            'owner_id' => $this->otherUser->getKey(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.grid_key', 'grids.members');

    $view = DatagridView::query()->findOrFail($response->json('data.id'));

    expect($view->section)->toBe('app')
        ->and($view->grid_key)->toBe('grids.members')
        ->and($view->owner_id)->toBe($this->owner->getKey());
});

/*
 | Compared as decoded structures, never as strings: the payload must come back
 | with the same values, in the same order, with the same types.
 */
test('saved grid state round-trips exactly through store, the table and index', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/grids/invoices/views', ['name' => 'Round trip', ...gridState()])
        ->assertCreated();

    expect(collect($response->json('data'))->only(['filter_sets', 'sorts', 'visibility'])->all())->toBe(gridState());

    $stored = DatagridView::query()->findOrFail($response->json('data.id'));

    expect(['filter_sets' => $stored->filter_sets, 'sorts' => $stored->sorts, 'visibility' => $stored->visibility])->toBe(gridState());

    $listed = $this->getJson('/api/grids/invoices/views')->assertOk()->json('data.0');

    expect(collect($listed)->only(['filter_sets', 'sorts', 'visibility'])->all())->toBe(gridState());
});

test('empty state lists stay empty lists rather than becoming null', function () {
    $this->actingAs($this->owner)
        ->postJson($this->views, ['name' => 'Empty', 'filter_sets' => [], 'sorts' => [], 'visibility' => []])
        ->assertCreated()
        ->assertJsonPath('data.filter_sets', [])
        ->assertJsonPath('data.sorts', [])
        ->assertJsonPath('data.visibility', []);
});

test('store with is_default makes the new view the only default of its grid', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->asDefault()->create();
    $otherGrid = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.invoices')->asDefault()->create();

    $response = $this->actingAs($this->owner)
        ->postJson($this->views, ['name' => 'New default', 'is_default' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    expect($previous->fresh()->is_default)->toBeFalse()
        ->and($otherGrid->fresh()->is_default)->toBeTrue()
        ->and(DatagridView::query()->forGrid('app', 'grids.members')->where('is_default', true)->pluck('id')->all())
        ->toBe([$response->json('data.id')]);
});

// --- update ---

test('update renames the view', function () {
    $this->actingAs($this->owner)
        ->patchJson($this->base, ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($this->view->fresh()->name)->toBe('Renamed');
});

test('update replaces the saved state and leaves the name alone', function () {
    $this->actingAs($this->owner)
        ->patchJson($this->base, [...gridState(), 'page_size' => 100])
        ->assertOk()
        ->assertJsonPath('data.name', 'Active members')
        ->assertJsonPath('data.page_size', 100);

    $fresh = $this->view->fresh();

    expect(['filter_sets' => $fresh->filter_sets, 'sorts' => $fresh->sorts, 'visibility' => $fresh->visibility])->toBe(gridState())
        ->and($fresh->page_size)->toBe(100);
});

test('update leaves every field the payload omits as it was', function () {
    $before = $this->view->fresh();

    $this->actingAs($this->owner)
        ->patchJson($this->base, ['sorts' => [['field' => 'column_Email', 'sort_operator' => 'desc']]])
        ->assertOk();

    $after = $this->view->fresh();

    expect($after->sorts)->toBe([['field' => 'column_Email', 'sort_operator' => 'desc']])
        ->and($after->filter_sets)->toBe($before->filter_sets)
        ->and($after->visibility)->toBe($before->visibility)
        ->and($after->page_size)->toBe($before->page_size)
        ->and($after->name)->toBe($before->name);
});

test('update does not move the view to another grid or change its default flag', function () {
    $this->actingAs($this->owner)
        ->patchJson($this->base, ['grid_key' => 'grids.invoices', 'is_default' => true, 'name' => 'Still here'])
        ->assertOk();

    $fresh = $this->view->fresh();

    expect($fresh->grid_key)->toBe('grids.members')
        ->and($fresh->is_default)->toBeFalse();
});

// --- destroy ---

test('the owner can delete their view', function () {
    $this->actingAs($this->owner)
        ->deleteJson($this->base)
        ->assertNoContent();

    expect(DatagridView::query()->whereKey($this->view->id)->exists())->toBeFalse();
});

test('deleting the default view leaves the grid with no default', function () {
    $this->view->forceFill(['is_default' => true])->save();
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create();

    $this->actingAs($this->owner)->deleteJson($this->base)->assertNoContent();

    expect(DatagridView::query()->forGrid('app', 'grids.members')->where('is_default', true)->exists())->toBeFalse();
});

// --- default ---

test('put default makes the view the actor\'s only default for the grid', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->asDefault()->create();

    $this->actingAs($this->owner)
        ->putJson("{$this->base}/default")
        ->assertOk()
        ->assertJsonPath('data.id', $this->view->id)
        ->assertJsonPath('data.is_default', true);

    expect($previous->fresh()->is_default)->toBeFalse();
});

test('delete default clears the view\'s default flag', function () {
    $this->view->forceFill(['is_default' => true])->save();

    $this->actingAs($this->owner)
        ->deleteJson("{$this->base}/default")
        ->assertOk()
        ->assertJsonPath('data.id', $this->view->id)
        ->assertJsonPath('data.is_default', false);

    expect($this->view->fresh()->is_default)->toBeFalse();
});

// --- access ---

/**
 * Every route that names one view, as [method, uri suffix, payload].
 *
 * @return array<string, array{string, string, array<string, mixed>}>
 */
function singleViewEndpoints(): array
{
    return [
        'update' => ['patch', '', ['name' => 'Taken over']],
        'destroy' => ['delete', '', []],
        'set default' => ['put', '/default', []],
        'clear default' => ['delete', '/default', []],
    ];
}

/**
 * Asserts $view is exactly as the suite created it: still there, its name
 * unchanged and not the default.
 */
function expectUntouched(DatagridView $view, string $name): void
{
    $fresh = $view->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe($name)
        ->and($fresh->is_default)->toBeFalse();
}

test('another owner\'s view is not found', function (string $method, string $suffix, array $payload) {
    $this->actingAs($this->otherUser)
        ->json($method, $this->base.$suffix, $payload)
        ->assertNotFound();

    expectUntouched($this->view, 'Active members');
})->with(singleViewEndpoints());

test('a view owned by an identity of another type sharing the actor\'s key is not found', function (string $method, string $suffix, array $payload) {
    $admin = Admin::factory()->create();
    $user = User::query()->find($admin->getKey()) ?? User::factory()->create(['id' => $admin->getKey()]);
    $adminView = DatagridView::factory()->ownedBy($admin)->forGrid('app', 'grids.members')->create(['name' => 'Admin view']);

    $this->actingAs($user)
        ->json($method, "{$this->views}/{$adminView->id}{$suffix}", $payload)
        ->assertNotFound();

    expectUntouched($adminView, 'Admin view');
})->with(singleViewEndpoints());

test('the actor\'s own view of the same grid key in another section is not found', function (string $method, string $suffix, array $payload) {
    $adminSectionView = DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'grids.members')->create(['name' => 'Admin section']);

    $this->actingAs($this->owner)
        ->json($method, "{$this->views}/{$adminSectionView->id}{$suffix}", $payload)
        ->assertNotFound();

    expectUntouched($adminSectionView, 'Admin section');
})->with(singleViewEndpoints());

test('the actor\'s own view is not found through another section\'s grid', function (string $method, string $suffix, array $payload) {
    $this->actingAs($this->owner)
        ->json($method, "/api/grids/admin-members/views/{$this->view->id}{$suffix}", $payload)
        ->assertNotFound();

    expectUntouched($this->view, 'Active members');
})->with(singleViewEndpoints());

test('a view saved on one grid is not found through another grid\'s routes', function (string $method, string $suffix, array $payload) {
    $this->actingAs($this->owner)
        ->json($method, "/api/grids/invoices/views/{$this->view->id}{$suffix}", $payload)
        ->assertNotFound();

    expectUntouched($this->view, 'Active members');
})->with(singleViewEndpoints());

test('a view saved on one grid is never listed by another grid', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/grids/invoices/views')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a view id that does not exist, or is not an id, is not found', function (string $method, string $suffix, array $payload, string $id) {
    $this->actingAs($this->owner)
        ->json($method, "{$this->views}/{$id}{$suffix}", $payload)
        ->assertNotFound();
})->with(singleViewEndpoints())->with(['999999', 'abc', '1.5', '-1']);

test('a guest is refused by every route', function (string $method, string $suffix, array $payload) {
    $this->json($method, $this->base.$suffix, $payload)->assertUnauthorized();

    expectUntouched($this->view, 'Active members');
})->with(singleViewEndpoints());

test('a guest can neither list nor create views', function () {
    $this->getJson($this->views)->assertUnauthorized();
    $this->postJson($this->views, ['name' => 'Nope'])->assertUnauthorized();

    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('a host that mounts a grid without authentication still refuses a guest with a 401', function () {
    Route::prefix('open')->group(fn () => Route::dataGrid(MembersDataGrid::class));

    $this->getJson('/open/grids/members/views')->assertUnauthorized();
    $this->postJson('/open/grids/members/views', ['name' => 'Nope'])->assertUnauthorized();
    $this->patchJson("/open/grids/members/views/{$this->view->id}", ['name' => 'Nope'])->assertUnauthorized();

    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('an actor the section shuts out may not list or create views there', function () {
    ScriptedAccess::$canUse = false;

    $this->actingAs($this->owner)->getJson('/api/grids/scripted-members/views')->assertForbidden();
    $this->actingAs($this->owner)->postJson('/api/grids/scripted-members/views', ['name' => 'Nope'])->assertForbidden();

    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('an actor the section shuts out may not touch even a view they own there', function (string $method, string $suffix, array $payload) {
    $scripted = DatagridView::factory()->ownedBy($this->owner)->forGrid('scripted', 'grids.scripted-members')->create(['name' => 'Kept']);
    ScriptedAccess::$canUse = false;

    $this->actingAs($this->owner)
        ->json($method, "/api/grids/scripted-members/views/{$scripted->id}{$suffix}", $payload)
        ->assertForbidden();

    expectUntouched($scripted, 'Kept');
})->with(singleViewEndpoints());

test('an actor the section admits may use every route there', function () {
    $this->actingAs($this->owner)->getJson('/api/grids/scripted-members/views')->assertOk();
    $id = $this->actingAs($this->owner)->postJson('/api/grids/scripted-members/views', ['name' => 'Allowed'])->assertCreated()->json('data.id');
    $this->actingAs($this->owner)->putJson("/api/grids/scripted-members/views/{$id}/default")->assertOk();
    $this->actingAs($this->owner)->deleteJson("/api/grids/scripted-members/views/{$id}/default")->assertOk();
    $this->actingAs($this->owner)->patchJson("/api/grids/scripted-members/views/{$id}", ['name' => 'Renamed'])->assertOk();
    $this->actingAs($this->owner)->deleteJson("/api/grids/scripted-members/views/{$id}")->assertNoContent();
});

// --- the grid's own permission ---

test('an actor the grid\'s own permission refuses may not list, create or touch its views', function () {
    Gate::define(GuardedMembersDataGrid::PERMISSION, fn (): bool => false);
    $guarded = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.guarded-members')->create(['name' => 'Guarded']);

    $this->actingAs($this->owner)->getJson('/api/grids/guarded-members/views')->assertForbidden();
    $this->actingAs($this->owner)->postJson('/api/grids/guarded-members/views', ['name' => 'Nope'])->assertForbidden();

    foreach (singleViewEndpoints() as [$method, $suffix, $payload]) {
        $this->actingAs($this->owner)
            ->json($method, "/api/grids/guarded-members/views/{$guarded->id}{$suffix}", $payload)
            ->assertForbidden();
    }

    expectUntouched($guarded, 'Guarded');
    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('an actor the grid\'s own permission admits may use its views', function () {
    Gate::define(GuardedMembersDataGrid::PERMISSION, fn (): bool => true);

    $id = $this->actingAs($this->owner)->postJson('/api/grids/guarded-members/views', ['name' => 'Allowed'])->assertCreated()->json('data.id');
    $this->actingAs($this->owner)->getJson('/api/grids/guarded-members/views')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->actingAs($this->owner)->putJson("/api/grids/guarded-members/views/{$id}/default")->assertOk();
});

// --- the grid's section ---

test('a grid whose section is not listed in config refuses to serve views', function () {
    config()->set('datagrid-views.sections', []);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->owner)->getJson($this->views))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section [app] is not listed in config('datagrid-views.sections').");
});

test('a grid whose section key another configured enum also claims refuses to serve views', function () {
    config()->set('datagrid-views.sections', [CollidingSection::class, TestSection::class]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->owner)->postJson($this->views, ['name' => 'Nope']))
        ->toThrow(InvalidArgumentException::class, 'registered by more than one enum');

    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});
