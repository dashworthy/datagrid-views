<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create(['name' => 'Active']);
});

test('store refuses a name the owner already uses on the grid', function (string $name) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['name' => $name]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(DatagridView::query()->forGrid('app', 'grids.members')->count())->toBe(1);
})->with([
    'same case' => ['Active'],
    'different case' => ['active'],
    'upper case' => ['ACTIVE'],
]);

test('store allows the same name on another grid, in another section, or for another owner', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/invoices/views', storeView(['name' => 'Active']))
        ->assertCreated();

    $this->actingAs($this->owner)
        ->postJson('/api/grids/admin-members/views', storeView(['name' => 'Active']))
        ->assertCreated();

    DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'grids.members')->create(['name' => 'Admin only']);

    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['name' => 'Admin only']))
        ->assertCreated();

    $this->actingAs(User::factory()->create())
        ->postJson('/api/grids/members/views', storeView(['name' => 'Active']))
        ->assertCreated();
});

test('update refuses a name another of the owner\'s views on the grid uses', function () {
    $other = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create(['name' => 'Other']);

    $this->actingAs($this->owner)
        ->patchJson("/api/grids/members/views/{$other->id}", ['name' => 'ACTIVE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($other->fresh()->name)->toBe('Other');
});

test('update lets a view keep its own name or change only its case', function (string $name) {
    $this->actingAs($this->owner)
        ->patchJson("/api/grids/members/views/{$this->view->id}", ['name' => $name])
        ->assertOk()
        ->assertJsonPath('data.name', $name);
})->with(['Active', 'ACTIVE']);

test('update allows a name the owner uses only on another grid', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.invoices')->create(['name' => 'Elsewhere']);

    $this->actingAs($this->owner)
        ->patchJson("/api/grids/members/views/{$this->view->id}", ['name' => 'Elsewhere'])
        ->assertOk();
});
