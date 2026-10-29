<?php

use Illuminate\Support\Facades\Schema;

test('the migration creates the datagrid_views table with every column the spec lists', function () {
    expect(Schema::hasTable('datagrid_views'))->toBeTrue()
        ->and(datagridViewColumns()->keys()->sort()->values()->all())->toBe([
            'created_at',
            'filter_sets',
            'grid_key',
            'id',
            'is_default',
            'name',
            'owner_id',
            'owner_type',
            'page_size',
            'section',
            'sorts',
            'updated_at',
            'visibility',
        ]);
});

test('a view always has an owner, a section, a grid and a name', function (string $column) {
    expect(datagridViewColumns()[$column]['nullable'])->toBeFalse();
})->with(['section', 'grid_key', 'owner_type', 'owner_id', 'name', 'is_default']);

test('the saved grid state is optional', function (string $column) {
    expect(datagridViewColumns()[$column]['nullable'])->toBeTrue();
})->with(['filter_sets', 'sorts', 'visibility', 'page_size']);

test('a view is not the default unless it is made one', function () {
    expect((string) datagridViewColumns()['is_default']['default'])->toBeIn(['0', "'0'", 'false']);
});

test('page_size is stored as an integer and is_default as a boolean', function () {
    expect(Schema::getColumnType('datagrid_views', 'page_size'))->toBeIn(['integer', 'smallint'])
        ->and(Schema::getColumnType('datagrid_views', 'is_default'))->toBeIn(['boolean', 'tinyint', 'integer']);
});

test('a name is unique per owner per grid per section, enforced by the database', function () {
    $unique = collect(Schema::getIndexes('datagrid_views'))
        ->firstWhere('name', 'datagrid_views_owner_grid_name_unique');

    expect($unique)->not->toBeNull()
        ->and($unique['unique'])->toBeTrue()
        ->and($unique['columns'])->toBe(['section', 'grid_key', 'owner_type', 'owner_id', 'name']);
});

test('the table is indexed for listing one owner\'s views of one grid', function () {
    $indexedColumnSets = collect(Schema::getIndexes('datagrid_views'))->pluck('columns');

    expect($indexedColumnSets)->toContain(['section', 'grid_key', 'owner_type', 'owner_id'])
        ->and($indexedColumnSets)->toContain(['section']);
});
