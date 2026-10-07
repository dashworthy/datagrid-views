<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Models\DatagridView;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing one grid's views. The section's access rule decides whether the
 * actor may have views here at all; the query itself (`visibleTo`) decides
 * which rows they see.
 */
class IndexDatagridViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [DatagridView::class, $this->route('section')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grid' => ['required', 'string', 'max:128'],
        ];
    }
}
