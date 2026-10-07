<?php

namespace Dashworthy\DatagridViews\Actions;

use Dashworthy\DatagridViews\Data\CreateDatagridViewData;
use Dashworthy\DatagridViews\Data\SetDefaultDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\DatagridViews\Support\UniqueDatagridViewName;
use Dashworthy\Foundry\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * The one way a view comes into existence. Its owner comes from the section's
 * access rule for the actor and is set structurally, never mass-assigned.
 *
 * A view created as the default goes through {@see SetDefaultDatagridViewAction}
 * inside this action's transaction, after the insert, so the owner's previous
 * default for the grid is cleared under its lock and a refused insert changes
 * nothing.
 *
 * @extends Action<CreateDatagridViewData, DatagridView>
 */
class CreateDatagridViewAction extends Action
{
    protected function handle(CreateDatagridViewData $data): DatagridView
    {
        $owner = app(DatagridViewSections::class)->find($data->section)->datagridViewAccess()->ownerFor($data->actor);

        $view = new DatagridView([
            'name' => $data->name,
            'filter_sets' => DatagridViewState::toWire($data->filterSets),
            'sorts' => DatagridViewState::toWire($data->sorts),
            'visibility' => DatagridViewState::toWire($data->visibility),
            'page_size' => $data->pageSize,
        ]);
        $view->section = $data->section;
        $view->grid_key = $data->gridKey;
        $view->is_default = false;
        $view->owner()->associate($owner);

        try {
            $view->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => UniqueDatagridViewName::MESSAGE]);
        }

        // The insert must come before SetDefault's locking read, never after
        // it: on InnoDB that read then waits on a concurrent create's
        // uncommitted row instead of missing it. Locking first would let two
        // creates each lock an empty range and both become the default.
        if ($data->isDefault) {
            SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($view));
        }

        return $view;
    }
}
