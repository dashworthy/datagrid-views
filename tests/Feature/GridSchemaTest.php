<?php

use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\CustomSchemaMembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\MembersDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\DataGrids\ReportsDataGrid;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

/*
 | The client learns a grid keeps saved views from the grid's schema, so it
 | knows to show the views menu and call the grid's view routes.
 */
test('a grid using the trait says so in its schema, and keeps the rest of the schema intact', function () {
    expect(MembersDataGrid::schema())
        ->toHaveKey('views', true)
        ->toHaveKeys(['visualization_key', 'columns', 'default_sorts', 'floating_filters'])
        ->and(MembersDataGrid::schema()['visualization_key'])->toBe('grids.members')
        ->and(collect(MembersDataGrid::schema())->except(['views', 'visualization_key'])->toJson())->toBe(collect(ReportsDataGrid::schema())->except('visualization_key')->toJson());
});

test('a grid that does not use the trait has no views flag in its schema', function () {
    expect(ReportsDataGrid::schema())->not->toHaveKey('views');
});

test('a grid that overrides getSchemaBody() still says it keeps views', function () {
    expect(CustomSchemaMembersDataGrid::schema())
        ->toHaveKey('views', true)
        ->toHaveKey('density', 'compact')
        ->toHaveKeys(['visualization_key', 'columns', 'default_sorts', 'floating_filters']);
});

test('the schema route answers with the views flag', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/grids/members/schema')
        ->assertOk()
        ->assertJsonPath('views', true)
        ->assertJsonPath('visualization_key', 'grids.members');

    $this->actingAs(User::factory()->create())
        ->postJson('/api/grids/custom-schema-members/schema')
        ->assertOk()
        ->assertJsonPath('views', true)
        ->assertJsonPath('density', 'compact');

    $this->actingAs(User::factory()->create())
        ->postJson('/api/grids/reports/schema')
        ->assertOk()
        ->assertJsonMissingPath('views');
});
