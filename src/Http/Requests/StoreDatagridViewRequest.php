<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Http\Concerns\ValidatesGridState;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Dashworthy\DatagridViews\Support\UniqueDatagridViewName;

/**
 * Saving a new view of the grid handling the request. The section and grid
 * come from that grid and the owner from the section's access rule; a grid
 * key, section or owner in the payload is ignored. Every state field is
 * optional, and null saves nothing for it.
 */
class StoreDatagridViewRequest extends DatagridViewRequest
{
    use ValidatesGridState;

    public function authorize(): bool
    {
        return $this->actorMaySeeGrid()
            && $this->actor()->can('create', [DatagridView::class, $this->sectionKey()]);
    }

    /**
     * Runs only after authorize() passed, so ownerFor() is asked only about an
     * actor the section lets use views.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $section = $this->sectionKey();
        $owner = app(DatagridViewSections::class)->find($section)->datagridViewAccess()->ownerFor($this->actor());

        return [
            'name' => ['required', 'string', 'max:100', UniqueDatagridViewName::forNewView($section, $this->gridKey(), $owner)],
            'is_default' => ['sometimes', 'boolean'],
            ...$this->gridStateRules(['sometimes', 'nullable']),
        ];
    }
}
