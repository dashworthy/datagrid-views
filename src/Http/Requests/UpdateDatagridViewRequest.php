<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Http\Concerns\ValidatesGridState;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\UniqueDatagridViewName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renaming a view and/or replacing parts of its saved state. Any subset of
 * name, filter_sets, sorts, visibility and page_size may be sent; an omitted
 * field is left as it is. Null is refused for every field: to save "none",
 * send an empty list. The grid and the default flag cannot change here.
 */
class UpdateDatagridViewRequest extends FormRequest
{
    use ValidatesGridState;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->datagridView());
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

    /**
     * The view being updated, already resolved inside the actor's visible
     * scope by the `{datagridView}` route binding.
     */
    public function datagridView(): DatagridView
    {
        return $this->route('datagridView');
    }
}
