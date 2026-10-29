<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\Admin;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->withState()->create(['name' => 'Active members']);
    $this->views = '/api/grids/members/views';
    $this->base = "{$this->views}/{$this->view->id}";
});

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
