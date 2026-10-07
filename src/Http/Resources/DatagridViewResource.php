<?php

namespace Dashworthy\DatagridViews\Http\Resources;

use Dashworthy\DatagridViews\Models\DatagridView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One view, as an explicit field list. The section and owner are left out: the
 * client already knows the section it asked, and a view is only ever returned
 * to its owner.
 *
 * @mixin DatagridView
 */
class DatagridViewResource extends JsonResource
{
    /**
     * @return array{id: int, grid_key: string, name: string, filter_sets: array<int, mixed>|null, sorts: array<int, mixed>|null, visibility: array<int, mixed>|null, page_size: int|null, is_default: bool, updated_at: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grid_key' => $this->grid_key,
            'name' => $this->name,
            'filter_sets' => $this->filter_sets,
            'sorts' => $this->sorts,
            'visibility' => $this->visibility,
            'page_size' => $this->page_size,
            'is_default' => (bool) $this->is_default,
            'updated_at' => $this->updated_at,
        ];
    }
}
