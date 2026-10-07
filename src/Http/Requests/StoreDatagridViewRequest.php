<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Http\Concerns\ValidatesGridState;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Dashworthy\DatagridViews\Support\UniqueDatagridViewName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saving a new view of one grid. The section comes from the route and the
 * owner from the section's access rule; a section or owner in the payload is
 * ignored. Every state field is optional, and null saves nothing for it.
 */
class StoreDatagridViewRequest extends FormRequest
{
    use ValidatesGridState;

    public function authorize(): bool
    {
        return $this->user()->can('create', [DatagridView::class, $this->route('section')]);
    }

    /**
     * Runs only after authorize() passed, so ownerFor() is asked only about an
     * actor the section lets use views.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $section = $this->route('section');
        $gridKey = $this->input('grid_key');

        $name = ['required', 'string', 'max:100'];

        if (is_string($gridKey)) {
            $owner = app(DatagridViewSections::class)->find($section)->datagridViewAccess()->ownerFor($this->user());
            $name[] = UniqueDatagridViewName::forNewView($section, $gridKey, $owner);
        }

        return [
            'grid_key' => ['required', 'string', 'max:128'],
            'name' => $name,
            'is_default' => ['sometimes', 'boolean'],
            ...$this->gridStateRules(['sometimes', 'nullable']),
        ];
    }
}
