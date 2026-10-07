<?php

namespace Dashworthy\DatagridViews\Support;

use Dashworthy\DatagridViews\Data\VisibilityData;
use Dashworthy\Visualizations\Data\FilterData;
use Dashworthy\Visualizations\Data\FilterSetData;
use Dashworthy\Visualizations\Data\SortData;
use Dashworthy\Visualizations\Enums\FilterOperator;
use Dashworthy\Visualizations\Enums\FilterSetOperator;
use Dashworthy\Visualizations\Enums\SortOperator;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Moves saved grid state between its wire shape (the arrays a client sends and
 * the JSON columns hold, the same shapes `dashboard_widgets` uses) and the
 * visualizations package's typed objects.
 *
 * Going through the objects is what keeps a stored view in the canonical
 * shape: a key the shape does not define is dropped, every value the shape
 * does define comes back unchanged and in order. Input is expected to be
 * validated already; these do not re-check it.
 */
final class DatagridViewState
{
    /**
     * @param  array<int, array{filters: array<int, array{field: string, value: mixed, filter_operator: string}>, filter_set_operator: string}>|null  $filterSets
     * @return list<FilterSetData>|null
     */
    public static function filterSets(?array $filterSets): ?array
    {
        if ($filterSets === null) {
            return null;
        }

        return array_values(array_map(fn (array $filterSet): FilterSetData => new FilterSetData(
            filters: collect($filterSet['filters'])->values()->map(fn (array $filter): FilterData => new FilterData(
                field: $filter['field'],
                value: $filter['value'],
                filterOperator: FilterOperator::from($filter['filter_operator']),
            )),
            filterOperator: FilterSetOperator::from($filterSet['filter_set_operator']),
        ), $filterSets));
    }

    /**
     * @param  array<int, array{field: string, sort_operator: string}>|null  $sorts
     * @return list<SortData>|null
     */
    public static function sorts(?array $sorts): ?array
    {
        if ($sorts === null) {
            return null;
        }

        return array_values(array_map(
            fn (array $sort): SortData => new SortData($sort['field'], SortOperator::from($sort['sort_operator'])),
            $sorts,
        ));
    }

    /**
     * @param  array<int, array{field: string, is_hidden: bool}>|null  $visibility
     * @return list<VisibilityData>|null
     */
    public static function visibility(?array $visibility): ?array
    {
        if ($visibility === null) {
            return null;
        }

        return array_values(array_map(
            fn (array $column): VisibilityData => new VisibilityData($column['field'], (bool) $column['is_hidden']),
            $visibility,
        ));
    }

    /**
     * The wire shape of a list of state objects. Null stays null and an empty
     * list stays an empty list: "saves nothing" and "saves none" differ.
     *
     * @param  list<Arrayable<string, mixed>>|null  $items
     * @return list<array<string, mixed>>|null
     */
    public static function toWire(?array $items): ?array
    {
        if ($items === null) {
            return null;
        }

        return array_map(fn (Arrayable $item): array => $item->toArray(), $items);
    }
}
