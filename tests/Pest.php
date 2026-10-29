<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

pest()->extend(TestCase::class)->use(DatabaseTransactions::class)->in('Feature', 'Unit');

/**
 * A full set of saved grid state in the wire shapes the client sends: two
 * filter sets (a rolling date pair and a multi-value list), two sorts and two
 * visibility entries, with string, list and integer filter values.
 *
 * @return array{filter_sets: list<array<string, mixed>>, sorts: list<array<string, string>>, visibility: list<array<string, mixed>>}
 */
function gridState(): array
{
    return [
        'filter_sets' => [
            [
                'filters' => [
                    ['field' => 'floating_filter_Created At', 'value' => '-30 days', 'filter_operator' => 'gte'],
                    ['field' => 'floating_filter_Created At', 'value' => '2026-03-31', 'filter_operator' => 'lte'],
                ],
                'filter_set_operator' => 'and',
            ],
            [
                'filters' => [
                    ['field' => 'column_Status', 'value' => ['active', 'invited'], 'filter_operator' => 'in'],
                    ['field' => 'column_Seats', 'value' => 3, 'filter_operator' => 'gt'],
                ],
                'filter_set_operator' => 'or',
            ],
        ],
        'sorts' => [
            ['field' => 'column_Name', 'sort_operator' => 'desc'],
            ['field' => 'column_Email', 'sort_operator' => 'asc'],
        ],
        'visibility' => [
            ['field' => 'column_Email', 'is_hidden' => true],
            ['field' => 'column_Name', 'is_hidden' => false],
        ],
    ];
}

/**
 * @return list<int>
 */
function defaultIds(string $section, string $gridKey): array
{
    return DatagridView::query()->forGrid($section, $gridKey)->where('is_default', true)->orderBy('id')->pluck('id')->all();
}

/**
 * @return Collection<string, array{name: string, type_name: string, nullable: bool, default: mixed}>
 */
function datagridViewColumns(): Collection
{
    return collect(Schema::getColumns('datagrid_views'))->keyBy('name');
}

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

/**
 * One filter set holding a single filter with $value.
 *
 * @return list<array<string, mixed>>
 */
function filterSetsWithValue(mixed $value): array
{
    return [['filters' => [['field' => 'column_Status', 'value' => $value, 'filter_operator' => 'equals']], 'filter_set_operator' => 'and']];
}

/**
 * Every route that names one view, as [method, uri suffix, payload].
 *
 * @return array<string, array{string, string, array<string, mixed>}>
 */
function singleViewEndpoints(): array
{
    return [
        'update' => ['patch', '', ['name' => 'Taken over']],
        'destroy' => ['delete', '', []],
        'set default' => ['put', '/default', []],
        'clear default' => ['delete', '/default', []],
    ];
}

/**
 * Asserts $view is exactly as the suite created it: still there, its name
 * unchanged and not the default.
 */
function expectUntouched(DatagridView $view, string $name): void
{
    $fresh = $view->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe($name)
        ->and($fresh->is_default)->toBeFalse();
}
