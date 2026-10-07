<?php

namespace Dashworthy\DatagridViews\Actions;

use Dashworthy\DatagridViews\Data\UpdateDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\DatagridViews\Support\UniqueDatagridViewName;
use Dashworthy\Foundry\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Renames a view and/or replaces parts of its saved grid state. Only the
 * fields the data carries change; see {@see UpdateDatagridViewData}.
 *
 * @extends Action<UpdateDatagridViewData, DatagridView>
 */
class UpdateDatagridViewAction extends Action
{
    protected function handle(UpdateDatagridViewData $data): DatagridView
    {
        $changes = array_filter([
            'name' => $data->name,
            'filter_sets' => DatagridViewState::toWire($data->filterSets),
            'sorts' => DatagridViewState::toWire($data->sorts),
            'visibility' => DatagridViewState::toWire($data->visibility),
            'page_size' => $data->pageSize,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $data->view->update($changes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => UniqueDatagridViewName::MESSAGE]);
        }

        return $data->view;
    }
}
