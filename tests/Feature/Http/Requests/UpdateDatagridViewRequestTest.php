<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create(['name' => 'Active']);
});

test('update refuses a bad name', function (mixed $name) {
    $this->actingAs($this->owner)
        ->patchJson("/api/grids/members/views/{$this->view->id}", ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($this->view->fresh()->name)->toBe('Active');
})->with([
    'null' => [null],
    'blank' => [''],
    'over 100 characters' => [str_repeat('n', 101)],
]);

test('update refuses a filter value that is an object', function () {
    $this->actingAs($this->owner)
        ->patchJson("/api/grids/members/views/{$this->view->id}", ['filter_sets' => filterSetsWithValue(['a' => 1])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filter_sets.0.filters.0.value');
});

test('update refuses grid state in the wrong shape, and null for a state field', function (array $state, string $field) {
    $before = $this->view->fresh();

    $this->actingAs($this->owner)
        ->patchJson("/api/grids/members/views/{$this->view->id}", $state)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($this->view->fresh()->updated_at->equalTo($before->updated_at))->toBeTrue();
})->with([
    'filter sets null' => [['filter_sets' => null], 'filter_sets'],
    'sorts null' => [['sorts' => null], 'sorts'],
    'visibility null' => [['visibility' => null], 'visibility'],
    'page size null' => [['page_size' => null], 'page_size'],
    'filter with an unknown operator' => [['filter_sets' => [['filters' => [['field' => 'column_Name', 'value' => 1, 'filter_operator' => 'like']], 'filter_set_operator' => 'and']]], 'filter_sets.0.filters.0.filter_operator'],
    'sort with an unknown operator' => [['sorts' => [['field' => 'column_Name', 'sort_operator' => 'up']]], 'sorts.0.sort_operator'],
    'visibility with a non-boolean flag' => [['visibility' => [['field' => 'column_Name', 'is_hidden' => 'maybe']]], 'visibility.0.is_hidden'],
    'page size over 500' => [['page_size' => 501], 'page_size'],
]);
