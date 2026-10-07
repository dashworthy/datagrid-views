<?php

namespace Dashworthy\DatagridViews\Data;

use Dashworthy\Foundry\Data\Data;
use Dashworthy\Visualizations\Data\FilterSetData;
use Dashworthy\Visualizations\Data\SortData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A new view: the grid it belongs to, its name, the grid state it saves, and
 * the actor whose section access rule decides its owner. Null state means the
 * view saves nothing for that part of the grid; an empty list saves "none".
 */
final readonly class CreateDatagridViewData extends Data
{
    /**
     * @param  list<FilterSetData>|null  $filterSets
     * @param  list<SortData>|null  $sorts
     * @param  list<VisibilityData>|null  $visibility
     */
    public function __construct(
        public string $section,
        public string $gridKey,
        public string $name,
        public Authenticatable&Model $actor,
        public ?array $filterSets = null,
        public ?array $sorts = null,
        public ?array $visibility = null,
        public ?int $pageSize = null,
        public bool $isDefault = false,
    ) {}
}
