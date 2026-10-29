<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->withState()->create(['name' => 'Active members']);
    $this->views = '/api/grids/members/views';
    $this->base = "{$this->views}/{$this->view->id}";
});

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
