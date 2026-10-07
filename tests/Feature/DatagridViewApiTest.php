<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\Admin;
use Dashworthy\DatagridViews\Tests\Fixtures\ScriptedAccess;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->withState()->create(['name' => 'Active members']);
    $this->base = "/api/app/datagrid-views/{$this->view->id}";
});

// --- index ---

test('index lists only the actor\'s own views of the requested grid', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'invoices')->create(['name' => 'Other grid']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'members')->create(['name' => 'Other section']);
    DatagridView::factory()->ownedBy($this->otherUser)->forGrid('app', 'members')->create(['name' => 'Other owner']);

    $this->actingAs($this->owner)
        ->getJson('/api/app/datagrid-views?grid=members')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->view->id)
        ->assertJsonPath('data.0.name', 'Active members');
});

test('index never lists a view owned by an identity of another type sharing the actor\'s key', function () {
    $admin = Admin::factory()->create();
    $user = User::query()->findOrFail($admin->getKey());

    DatagridView::factory()->ownedBy($admin)->forGrid('app', 'invoices')->create(['name' => 'Admin view']);

    $this->actingAs($user)
        ->getJson('/api/app/datagrid-views?grid=invoices')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('index lists the default view first, then the rest by name ignoring case', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create(['name' => 'beta']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create(['name' => 'Zulu']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create(['name' => 'Alpha']);

    $this->actingAs($this->owner)
        ->getJson('/api/app/datagrid-views?grid=members')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Zulu', 'Active members', 'Alpha', 'beta']);
});

test('index answers with exactly the resource fields', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/app/datagrid-views?grid=members')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'grid_key', 'name', 'filter_sets', 'sorts', 'visibility', 'page_size', 'is_default', 'updated_at']]])
        ->assertJsonMissingPath('data.0.owner_id')
        ->assertJsonMissingPath('data.0.owner_type')
        ->assertJsonMissingPath('data.0.section');
});

test('index requires the grid key', function (mixed $grid) {
    $this->actingAs($this->owner)
        ->getJson('/api/app/datagrid-views'.($grid === null ? '' : '?grid='.$grid))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('grid');
})->with([
    'missing' => [null],
    'too long' => [str_repeat('g', 129)],
]);

// --- show ---

test('the owner can show their view', function () {
    $this->actingAs($this->owner)
        ->getJson($this->base)
        ->assertOk()
        ->assertJsonPath('data.id', $this->view->id)
        ->assertJsonPath('data.grid_key', 'members')
        ->assertJsonPath('data.page_size', 25)
        ->assertJsonPath('data.is_default', false);
});

// --- store ---

test('store creates a view owned by the actor in the route\'s section and answers 201', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/app/datagrid-views', ['grid_key' => 'members', 'name' => 'Recent', ...gridState(), 'page_size' => 50])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Recent')
        ->assertJsonPath('data.grid_key', 'members')
        ->assertJsonPath('data.page_size', 50)
        ->assertJsonPath('data.is_default', false);

    $view = DatagridView::query()->findOrFail($response->json('data.id'));

    expect($view->section)->toBe('app')
        ->and($view->owner_type)->toBe($this->owner->getMorphClass())
        ->and($view->owner_id)->toBe($this->owner->getKey());
});

test('store needs only a grid key and a name', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/app/datagrid-views', ['grid_key' => 'members', 'name' => 'Bare'])
        ->assertCreated()
        ->assertJsonPath('data.filter_sets', null)
        ->assertJsonPath('data.sorts', null)
        ->assertJsonPath('data.visibility', null)
        ->assertJsonPath('data.page_size', null)
        ->assertJsonPath('data.is_default', false);
});

test('store ignores an owner or section named in the payload', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/app/datagrid-views', [
            'grid_key' => 'members',
            'name' => 'Sneaky',
            'section' => 'admin',
            'owner_type' => $this->otherUser->getMorphClass(),
            'owner_id' => $this->otherUser->getKey(),
        ])
        ->assertCreated();

    $view = DatagridView::query()->findOrFail($response->json('data.id'));

    expect($view->section)->toBe('app')
        ->and($view->owner_id)->toBe($this->owner->getKey());
});

/*
 | Compared as decoded structures, never as strings: the payload must come back
 | with the same values, in the same order, with the same types.
 */
test('saved grid state round-trips exactly through store, the table and index', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/app/datagrid-views', ['grid_key' => 'invoices', 'name' => 'Round trip', ...gridState()])
        ->assertCreated();

    expect(collect($response->json('data'))->only(['filter_sets', 'sorts', 'visibility'])->all())->toBe(gridState());

    $stored = DatagridView::query()->findOrFail($response->json('data.id'));

    expect(['filter_sets' => $stored->filter_sets, 'sorts' => $stored->sorts, 'visibility' => $stored->visibility])->toBe(gridState());

    $listed = $this->getJson('/api/app/datagrid-views?grid=invoices')->assertOk()->json('data.0');

    expect(collect($listed)->only(['filter_sets', 'sorts', 'visibility'])->all())->toBe(gridState());
});

test('empty state lists stay empty lists rather than becoming null', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/app/datagrid-views', ['grid_key' => 'members', 'name' => 'Empty', 'filter_sets' => [], 'sorts' => [], 'visibility' => []])
        ->assertCreated()
        ->assertJsonPath('data.filter_sets', [])
        ->assertJsonPath('data.sorts', [])
        ->assertJsonPath('data.visibility', []);
});

test('store with is_default makes the new view the only default of its grid', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();
    $otherGrid = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'invoices')->asDefault()->create();

    $response = $this->actingAs($this->owner)
        ->postJson('/api/app/datagrid-views', ['grid_key' => 'members', 'name' => 'New default', 'is_default' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    expect($previous->fresh()->is_default)->toBeFalse()
        ->and($otherGrid->fresh()->is_default)->toBeTrue()
        ->and(DatagridView::query()->forGrid('app', 'members')->where('is_default', true)->pluck('id')->all())
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
        ->patchJson($this->base, ['grid_key' => 'invoices', 'is_default' => true, 'name' => 'Still here'])
        ->assertOk();

    $fresh = $this->view->fresh();

    expect($fresh->grid_key)->toBe('members')
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
    $this->view->update(['is_default' => true]);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();

    $this->actingAs($this->owner)->deleteJson($this->base)->assertNoContent();

    expect(DatagridView::query()->forGrid('app', 'members')->where('is_default', true)->exists())->toBeFalse();
});

// --- default ---

test('put default makes the view the actor\'s only default for the grid', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();

    $this->actingAs($this->owner)
        ->putJson("{$this->base}/default")
        ->assertOk()
        ->assertJsonPath('data.id', $this->view->id)
        ->assertJsonPath('data.is_default', true);

    expect($previous->fresh()->is_default)->toBeFalse();
});

test('delete default clears the view\'s default flag', function () {
    $this->view->update(['is_default' => true]);

    $this->actingAs($this->owner)
        ->deleteJson("{$this->base}/default")
        ->assertOk()
        ->assertJsonPath('data.id', $this->view->id)
        ->assertJsonPath('data.is_default', false);

    expect($this->view->fresh()->is_default)->toBeFalse();
});

// --- access ---

/**
 * Every endpoint that names one view, as [method, uri suffix, payload].
 *
 * @return array<string, array{string, string, array<string, mixed>}>
 */
function singleViewEndpoints(): array
{
    return [
        'show' => ['get', '', []],
        'update' => ['patch', '', ['name' => 'Taken over']],
        'destroy' => ['delete', '', []],
        'set default' => ['put', '/default', []],
        'clear default' => ['delete', '/default', []],
    ];
}

test('another owner\'s view is not found', function (string $method, string $suffix, array $payload) {
    $this->actingAs($this->otherUser)
        ->json($method, $this->base.$suffix, $payload)
        ->assertNotFound();

    expect($this->view->fresh()->name)->toBe('Active members')
        ->and($this->view->fresh()->is_default)->toBeFalse();
})->with(singleViewEndpoints());

test('a view owned by an identity of another type sharing the actor\'s key is not found', function (string $method, string $suffix, array $payload) {
    $admin = Admin::factory()->create();
    $user = User::query()->find($admin->getKey()) ?? User::factory()->create(['id' => $admin->getKey()]);
    $adminView = DatagridView::factory()->ownedBy($admin)->forGrid('app', 'members')->create();

    $this->actingAs($user)
        ->json($method, "/api/app/datagrid-views/{$adminView->id}{$suffix}", $payload)
        ->assertNotFound();
})->with(singleViewEndpoints());

test('the actor\'s own view from another section is not found', function (string $method, string $suffix, array $payload) {
    $this->actingAs($this->owner)
        ->json($method, "/api/admin/datagrid-views/{$this->view->id}{$suffix}", $payload)
        ->assertNotFound();

    expect($this->view->fresh()->exists)->toBeTrue();
})->with(singleViewEndpoints());

test('a view id that does not exist is not found', function (string $method, string $suffix, array $payload) {
    $this->actingAs($this->owner)
        ->json($method, "/api/app/datagrid-views/999999{$suffix}", $payload)
        ->assertNotFound();
})->with(singleViewEndpoints());

test('a guest is refused by every endpoint', function (string $method, string $suffix, array $payload) {
    $this->json($method, $this->base.$suffix, $payload)->assertUnauthorized();
})->with(singleViewEndpoints());

test('a guest can neither list nor create views', function () {
    $this->getJson('/api/app/datagrid-views?grid=members')->assertUnauthorized();
    $this->postJson('/api/app/datagrid-views', ['grid_key' => 'members', 'name' => 'Nope'])->assertUnauthorized();

    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('an actor the section shuts out may not list or create views there', function () {
    ScriptedAccess::$canUse = false;

    $this->actingAs($this->owner)->getJson('/api/scripted/datagrid-views?grid=members')->assertForbidden();
    $this->actingAs($this->owner)->postJson('/api/scripted/datagrid-views', ['grid_key' => 'members', 'name' => 'Nope'])->assertForbidden();

    expect(DatagridView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('an actor the section shuts out may not touch even a view they own there', function (string $method, string $suffix, array $payload) {
    $scripted = DatagridView::factory()->ownedBy($this->owner)->forGrid('scripted', 'members')->create(['name' => 'Kept']);
    ScriptedAccess::$canUse = false;

    $this->actingAs($this->owner)
        ->json($method, "/api/scripted/datagrid-views/{$scripted->id}{$suffix}", $payload)
        ->assertForbidden();

    expect($scripted->fresh())->not->toBeNull()
        ->and($scripted->fresh()->name)->toBe('Kept')
        ->and($scripted->fresh()->is_default)->toBeFalse();
})->with(singleViewEndpoints());

test('an actor the section admits may use every endpoint there', function () {
    $this->actingAs($this->owner)->getJson('/api/scripted/datagrid-views?grid=members')->assertOk();
    $id = $this->actingAs($this->owner)->postJson('/api/scripted/datagrid-views', ['grid_key' => 'members', 'name' => 'Allowed'])->assertCreated()->json('data.id');
    $this->actingAs($this->owner)->putJson("/api/scripted/datagrid-views/{$id}/default")->assertOk();
});
