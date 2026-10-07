<?php

namespace Dashworthy\DatagridViews\Data;

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Visualizations\Data\FilterSetData;
use Dashworthy\Visualizations\Data\SortData;

/**
 * A partial update: null means "leave alone", never "clear". To save "no
 * filters", "no sorts" or "nothing hidden", pass an empty list. A view's grid
 * and default flag are not changed here; the default has its own actions.
 */
final readonly class UpdateDatagridViewData extends Data
{
    /**
     * @param  list<FilterSetData>|null  $filterSets
     * @param  list<SortData>|null  $sorts
     * @param  list<VisibilityData>|null  $visibility
     */
    public function __construct(
        public DatagridView $view,
        public ?string $name = null,
        public ?array $filterSets = null,
        public ?array $sorts = null,
        public ?array $visibility = null,
        public ?int $pageSize = null,
    ) {}
}
