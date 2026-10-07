<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Http\Concerns\ValidatesGridState;
use Dashworthy\DatagridViews\Support\UniqueDatagridViewName;

/**
 * Renaming a view and/or replacing parts of its saved state. Any subset of
 * name, filter_sets, sorts, visibility and page_size may be sent; an omitted
 * field is left as it is. Null is refused for every field: to save "none",
 * send an empty list. The grid and the default flag cannot change here.
 */
class UpdateDatagridViewRequest extends DatagridViewRequest
{
    use ValidatesGridState;

    /**
     * Looks the view up first, so a view outside the actor's scope 404s
     * before any 403 could say it exists.
     */
    public function authorize(): bool
    {
        $view = $this->datagridView();

        return $this->actorMaySeeGrid() && $this->actor()->can('update', $view);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100', UniqueDatagridViewName::forRenaming($this->datagridView())],
            ...$this->gridStateRules(['sometimes']),
        ];
    }
}
