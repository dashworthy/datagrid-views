<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * @return Collection<string, array{name: string, type_name: string, nullable: bool, default: mixed}>
 */
function datagridViewColumns(): Collection
{
    return collect(Schema::getColumns('datagrid_views'))->keyBy('name');
}

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

test('the table is indexed for listing one owner\'s views of one grid', function () {
    $indexedColumnSets = collect(Schema::getIndexes('datagrid_views'))->pluck('columns');

    expect($indexedColumnSets)->toContain(['section', 'grid_key', 'owner_type', 'owner_id'])
        ->and($indexedColumnSets)->toContain(['section']);
});
