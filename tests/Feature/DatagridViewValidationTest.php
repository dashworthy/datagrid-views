<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'grids.members')->create(['name' => 'Active']);
});

/**
 * A store payload: a fresh name plus $payload. The grid is never sent; it is
 * the grid whose route handles the request.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function storeView(array $payload): array
{
    return ['name' => 'Fresh', ...$payload];
}

// --- name ---

test('store refuses a bad name', function (array $payload) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with([
    'name missing' => [[]],
    'name blank' => [['name' => '   ']],
    'name not a string' => [['name' => ['x']]],
    'name over 100 characters' => [['name' => str_repeat('n', 101)]],
]);

test('store needs no grid key, and a malformed one in the payload is ignored rather than validated', function (mixed $gridKey) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['grid_key' => $gridKey]))
        ->assertCreated()
        ->assertJsonPath('data.grid_key', 'grids.members');
})->with([
    'not a string' => [12],
    'over 128 characters' => [str_repeat('g', 129)],
]);

test('store accepts a name of exactly 100 characters', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['name' => str_repeat('n', 100)]))
        ->assertCreated();
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

// --- name uniqueness ---

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

// --- grid state ---

test('store refuses grid state in the wrong shape', function (array $state, string $field) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView($state))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'filter sets not a list' => [['filter_sets' => ['first' => ['filters' => [], 'filter_set_operator' => 'and']]], 'filter_sets'],
    'filter sets a string' => [['filter_sets' => 'everything'], 'filter_sets'],
    'filter set without filters' => [['filter_sets' => [['filter_set_operator' => 'and']]], 'filter_sets.0.filters'],
    'filter set without operator' => [['filter_sets' => [['filters' => []]]], 'filter_sets.0.filter_set_operator'],
    'filter set with an unknown operator' => [['filter_sets' => [['filters' => [], 'filter_set_operator' => 'xor']]], 'filter_sets.0.filter_set_operator'],
    'filter without a field' => [['filter_sets' => [['filters' => [['value' => 1, 'filter_operator' => 'equals']], 'filter_set_operator' => 'and']]], 'filter_sets.0.filters.0.field'],
    'filter without a value' => [['filter_sets' => [['filters' => [['field' => 'column_Name', 'filter_operator' => 'equals']], 'filter_set_operator' => 'and']]], 'filter_sets.0.filters.0.value'],
    'filter with an unknown operator' => [['filter_sets' => [['filters' => [['field' => 'column_Name', 'value' => 1, 'filter_operator' => 'like']], 'filter_set_operator' => 'and']]], 'filter_sets.0.filters.0.filter_operator'],
    'sorts not a list' => [['sorts' => ['column_Name' => 'asc']], 'sorts'],
    'sort without a field' => [['sorts' => [['sort_operator' => 'asc']]], 'sorts.0.field'],
    'sort with an unknown operator' => [['sorts' => [['field' => 'column_Name', 'sort_operator' => 'up']]], 'sorts.0.sort_operator'],
    'visibility not a list' => [['visibility' => ['column_Name' => true]], 'visibility'],
    'visibility without a field' => [['visibility' => [['is_hidden' => true]]], 'visibility.0.field'],
    'visibility with a non-boolean flag' => [['visibility' => [['field' => 'column_Name', 'is_hidden' => 'maybe']]], 'visibility.0.is_hidden'],
    'page size zero' => [['page_size' => 0], 'page_size'],
    'page size over 500' => [['page_size' => 501], 'page_size'],
    'page size fractional' => [['page_size' => 1.5], 'page_size'],
    'page size a word' => [['page_size' => 'many'], 'page_size'],
    'is_default not a boolean' => [['is_default' => 'sure'], 'is_default'],
]);

test('store accepts page sizes at both ends of the range', function (int $pageSize) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['name' => "Size {$pageSize}", 'page_size' => $pageSize]))
        ->assertCreated()
        ->assertJsonPath('data.page_size', $pageSize);
})->with([1, 500]);

/**
 * One filter set holding a single filter with $value.
 *
 * @return list<array<string, mixed>>
 */
function filterSetsWithValue(mixed $value): array
{
    return [['filters' => [['field' => 'column_Status', 'value' => $value, 'filter_operator' => 'equals']], 'filter_set_operator' => 'and']];
}

/*
 | A filter value is what visualizations' FilterData carries: a scalar, null,
 | or a flat list of scalars (an `in` / `notIn` filter).
 */
test('store accepts a filter value that is a scalar, null or a flat list of scalars', function (mixed $value) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['filter_sets' => filterSetsWithValue($value)]))
        ->assertCreated()
        ->assertJsonPath('data.filter_sets', filterSetsWithValue($value));
})->with([
    'string' => ['-30 days'],
    'integer' => [3],
    'float' => [2.5],
    'boolean' => [true],
    'null' => [null],
    'empty list' => [[]],
    'list of scalars' => [['active', 2, false, null]],
]);

test('store refuses a filter value that is an object, a nested list or an oversized list', function (mixed $value) {
    $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView(['filter_sets' => filterSetsWithValue($value)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filter_sets.0.filters.0.value');
})->with([
    'object' => [['from' => '2026-01-01', 'to' => '2026-02-01']],
    'nested list' => [[['active'], ['invited']]],
    'list holding an object' => [['active', ['nested' => true]]],
    'list over 500 entries' => [range(1, 501)],
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

test('state keys the wire shape does not define are dropped rather than stored', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/grids/members/views', storeView([
            'filter_sets' => [['filters' => [['field' => 'column_Name', 'value' => 'a', 'filter_operator' => 'contains', 'extra' => 'x']], 'filter_set_operator' => 'and', 'extra' => 'y']],
            'sorts' => [['field' => 'column_Name', 'sort_operator' => 'asc', 'extra' => 'z']],
            'visibility' => [['field' => 'column_Name', 'is_hidden' => true, 'extra' => 'w']],
        ]))
        ->assertCreated();

    $stored = DatagridView::query()->findOrFail($response->json('data.id'));

    expect($stored->filter_sets)->toBe([['filters' => [['field' => 'column_Name', 'value' => 'a', 'filter_operator' => 'contains']], 'filter_set_operator' => 'and']])
        ->and($stored->sorts)->toBe([['field' => 'column_Name', 'sort_operator' => 'asc']])
        ->and($stored->visibility)->toBe([['field' => 'column_Name', 'is_hidden' => true]]);
});
