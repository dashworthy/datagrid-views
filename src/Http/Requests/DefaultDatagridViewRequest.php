<?php

namespace Dashworthy\DatagridViews\Http\Requests;

/**
 * Making a view its grid's default, or clearing that. Takes no input.
 */
class DefaultDatagridViewRequest extends DatagridViewRequest
{
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
        return [];
    }
}
