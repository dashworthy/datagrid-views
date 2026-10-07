<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Models\DatagridView;

/**
 * Listing the grid's views. The section's access rule decides whether the
 * actor may have views here at all; the query itself (`visibleTo`) decides
 * which rows they see. The grid is the one handling the request, so nothing
 * is read from the query string.
 */
class IndexDatagridViewRequest extends DatagridViewRequest
{
    public function authorize(): bool
    {
        return $this->actorMaySeeGrid()
            && $this->actor()->can('viewAny', [DatagridView::class, $this->sectionKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
