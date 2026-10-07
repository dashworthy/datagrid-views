<?php

namespace Dashworthy\DatagridViews\Http\Concerns;

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
 * be present but may be anything the client's filter uses: a string, a
 * number, a list, or null.
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
            'filter_sets.*.filters.*.value' => ['present'],
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
}
