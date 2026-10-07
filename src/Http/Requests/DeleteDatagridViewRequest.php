<?php

namespace Dashworthy\DatagridViews\Http\Requests;

/**
 * Deleting a view. Takes no input.
 */
class DeleteDatagridViewRequest extends DatagridViewRequest
{
    /**
     * Looks the view up first, so a view outside the actor's scope 404s
     * before any 403 could say it exists.
     */
    public function authorize(): bool
    {
        $view = $this->datagridView();

        return $this->actorMaySeeGrid() && $this->actor()->can('delete', $view);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
