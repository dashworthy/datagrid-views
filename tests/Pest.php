<?php

use Dashworthy\DatagridViews\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

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
