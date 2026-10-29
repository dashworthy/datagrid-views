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
