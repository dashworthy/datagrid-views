<?php

namespace Dashworthy\DatagridViews\Http\Controllers;

use Dashworthy\DatagridViews\Actions\ClearDefaultDatagridViewAction;
use Dashworthy\DatagridViews\Actions\CreateDatagridViewAction;
use Dashworthy\DatagridViews\Actions\DeleteDatagridViewAction;
use Dashworthy\DatagridViews\Actions\SetDefaultDatagridViewAction;
use Dashworthy\DatagridViews\Actions\UpdateDatagridViewAction;
use Dashworthy\DatagridViews\Data\ClearDefaultDatagridViewData;
use Dashworthy\DatagridViews\Data\CreateDatagridViewData;
use Dashworthy\DatagridViews\Data\DeleteDatagridViewData;
use Dashworthy\DatagridViews\Data\SetDefaultDatagridViewData;
use Dashworthy\DatagridViews\Data\UpdateDatagridViewData;
use Dashworthy\DatagridViews\Http\Requests\IndexDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\StoreDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\UpdateDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Resources\DatagridViewResource;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * The JSON API for one section's saved views, mounted by
 * `Route::datagridViewSection()`.
 *
 * Actions that act on one view read it from the route bag rather than
 * declaring a typed parameter: `Controller::callAction` passes route
 * parameters positionally, so a typed parameter would line up with the wrong
 * value as soon as a host mounts the section inside a group carrying its own
 * URL parameter (a `{tenant}` prefix). The view itself is resolved by the
 * `{datagridView}` binding registered in DatagridViewsServiceProvider, inside
 * the actor's visible scope for the route's section.
 */
class DatagridViewApiController extends Controller
{
    /**
     * The actor's views of one grid: the default first, then by name ignoring
     * case, with the id as the tie-break.
     */
    public function index(IndexDatagridViewRequest $request): AnonymousResourceCollection
    {
        $views = DatagridView::query()
            ->visibleTo($request->user(), $request->route('section'))
            ->where('grid_key', $request->validated('grid'))
            ->orderByDesc('is_default')
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get();

        return DatagridViewResource::collection($views);
    }

    public function show(Request $request): DatagridViewResource
    {
        $view = $this->boundView($request);

        Gate::authorize('view', $view);

        return DatagridViewResource::make($view);
    }

    public function store(StoreDatagridViewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $view = CreateDatagridViewAction::run(new CreateDatagridViewData(
            section: $request->route('section'),
            gridKey: $validated['grid_key'],
            name: $validated['name'],
            actor: $request->user(),
            filterSets: DatagridViewState::filterSets($validated['filter_sets'] ?? null),
            sorts: DatagridViewState::sorts($validated['sorts'] ?? null),
            visibility: DatagridViewState::visibility($validated['visibility'] ?? null),
            pageSize: isset($validated['page_size']) ? (int) $validated['page_size'] : null,
            isDefault: (bool) ($validated['is_default'] ?? false),
        ));

        return DatagridViewResource::make($view)->response()->setStatusCode(201);
    }

    public function update(UpdateDatagridViewRequest $request): DatagridViewResource
    {
        $validated = $request->validated();

        $view = UpdateDatagridViewAction::run(new UpdateDatagridViewData(
            view: $request->datagridView(),
            name: $validated['name'] ?? null,
            filterSets: DatagridViewState::filterSets($validated['filter_sets'] ?? null),
            sorts: DatagridViewState::sorts($validated['sorts'] ?? null),
            visibility: DatagridViewState::visibility($validated['visibility'] ?? null),
            pageSize: isset($validated['page_size']) ? (int) $validated['page_size'] : null,
        ));

        return DatagridViewResource::make($view);
    }

    public function destroy(Request $request): JsonResponse
    {
        $view = $this->boundView($request);

        Gate::authorize('delete', $view);

        DeleteDatagridViewAction::run(new DeleteDatagridViewData($view));

        return response()->json(null, 204);
    }

    public function setDefault(Request $request): DatagridViewResource
    {
        $view = $this->boundView($request);

        Gate::authorize('update', $view);

        return DatagridViewResource::make(SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($view)));
    }

    public function clearDefault(Request $request): DatagridViewResource
    {
        $view = $this->boundView($request);

        Gate::authorize('update', $view);

        return DatagridViewResource::make(ClearDefaultDatagridViewAction::run(new ClearDefaultDatagridViewData($view)));
    }

    private function boundView(Request $request): DatagridView
    {
        return $request->route('datagridView');
    }
}
