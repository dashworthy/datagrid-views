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
