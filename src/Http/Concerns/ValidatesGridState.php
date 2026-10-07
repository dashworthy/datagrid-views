<?php

namespace Dashworthy\DatagridViews\Http\Concerns;

use Closure;
use Dashworthy\Visualizations\Data\FilterData;
use Dashworthy\Visualizations\Data\FilterSetData;
use Dashworthy\Visualizations\Data\SortData;
use Dashworthy\Visualizations\Enums\FilterOperator;
use Dashworthy\Visualizations\Enums\FilterSetOperator;
use Dashworthy\Visualizations\Enums\SortOperator;
use Illuminate\Validation\Rule;

/**
 * The rules for saved grid state, in the wire shapes the visualizations
 * package's {@see FilterSetData} and {@see SortData} serialize to (and that
 * `dashboard_widgets` stores), plus the `{field, is_hidden}` visibility shape.
 *
 * Each state field must be a JSON list, not an object. A filter's `value` must
 * be present and be what a {@see FilterData} value carries: a scalar (string,
 * number or boolean), null, or a flat list of at most 500 of those (an `in` /
 * `notIn` filter). Objects and nested lists are refused.
 */
trait ValidatesGridState
{
    /**
     * @param  list<string>  $presence  how a top-level state field may be
     *                                  given: `['sometimes', 'nullable']` on create, `['sometimes']` on update
     * @return array<string, mixed>
     */
    protected function gridStateRules(array $presence): array
    {
        return [
            'filter_sets' => [...$presence, 'list', 'max:100'],
            'filter_sets.*' => ['array'],
            'filter_sets.*.filters' => ['present', 'list', 'max:100'],
            'filter_sets.*.filters.*' => ['array'],
            'filter_sets.*.filters.*.field' => ['required', 'string', 'max:255'],
            'filter_sets.*.filters.*.value' => ['present', $this->filterValueRule()],
            'filter_sets.*.filters.*.filter_operator' => ['required', Rule::enum(FilterOperator::class)],
            'filter_sets.*.filter_set_operator' => ['required', Rule::enum(FilterSetOperator::class)],

            'sorts' => [...$presence, 'list', 'max:100'],
            'sorts.*' => ['array'],
            'sorts.*.field' => ['required', 'string', 'max:255'],
            'sorts.*.sort_operator' => ['required', Rule::enum(SortOperator::class)],

            'visibility' => [...$presence, 'list', 'max:500'],
            'visibility.*' => ['array'],
            'visibility.*.field' => ['required', 'string', 'max:255'],
            'visibility.*.is_hidden' => ['required', 'boolean'],

            'page_size' => [...$presence, 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * Accepts a scalar, null, or a list of at most 500 scalars or nulls.
     */
    private function filterValueRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || is_scalar($value)) {
                return;
            }

            $isFlatList = is_array($value)
                && array_is_list($value)
                && count($value) <= 500
                && array_all($value, fn (mixed $item): bool => $item === null || is_scalar($item));

            if (! $isFlatList) {
                $fail('The :attribute must be a single value or a list of at most 500 single values.');
            }
        };
    }
}
