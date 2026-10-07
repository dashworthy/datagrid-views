<?php

namespace Dashworthy\DatagridViews\Concerns;

use Dashworthy\DatagridViews\Actions\ClearDefaultDatagridViewAction;
use Dashworthy\DatagridViews\Actions\CreateDatagridViewAction;
use Dashworthy\DatagridViews\Actions\DeleteDatagridViewAction;
use Dashworthy\DatagridViews\Actions\SetDefaultDatagridViewAction;
use Dashworthy\DatagridViews\Actions\UpdateDatagridViewAction;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Data\ClearDefaultDatagridViewData;
use Dashworthy\DatagridViews\Data\CreateDatagridViewData;
use Dashworthy\DatagridViews\Data\DeleteDatagridViewData;
use Dashworthy\DatagridViews\Data\SetDefaultDatagridViewData;
use Dashworthy\DatagridViews\Data\UpdateDatagridViewData;
use Dashworthy\DatagridViews\Http\Requests\DatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\DefaultDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\DeleteDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\IndexDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\StoreDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Requests\UpdateDatagridViewRequest;
use Dashworthy\DatagridViews\Http\Resources\DatagridViewResource;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\Visualizations\DataGrids\Abstracts\DataGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gives a data grid saved views. Use it on a visualizations DataGrid that
 * implements {@see HasDatagridViews}; `Route::dataGrid()` then mounts one route
 * per hook below, under the grid's own route name and path:
 *
 * | Hook                   | Route                                    | Name                              |
 * | ---------------------- | ---------------------------------------- | --------------------------------- |
 * | handleViews            | GET    {path}/views                      | {name}.views                      |
 * | handleViewStore        | POST   {path}/views                      | {name}.views.store                |
 * | handleViewUpdate       | PATCH  {path}/views/{view}               | {name}.views.update               |
 * | handleViewDestroy      | DELETE {path}/views/{view}               | {name}.views.destroy              |
 * | handleViewDefault      | PUT    {path}/views/{view}/default       | {name}.views.default              |
 * | handleViewClearDefault | DELETE {path}/views/{view}/default       | {name}.views.clear-default        |
 *
 * Each hook takes the plain request `Route::dataGrid()` hands it and builds
 * its form request from it with {@see DatagridViewRequest::forGrid()}, which
 * takes the section from `getDatagridViewSection()`, the grid key from
 * `getVisualizationKey()` and the actor from the authenticated user, looks
 * `{view}` up inside that scope (404 otherwise), authorizes through the
 * DatagridViewPolicy and the grid's own permission (403) and validates the
 * body (422) before the hook runs its action.
 *
 * The signatures match visualizations' view-hook declarations: the plain
 * request, plus `string $view` on the hooks that name one view. That `$view`
 * argument is not used: Laravel passes route parameters positionally, so
 * behind a host prefix or domain with its own parameter (a `{tenant}`) it
 * holds that parameter instead. `{view}` is read from the route by name and
 * looked up as a scalar id, never bound to a model. Every hook answers with a
 * JsonResponse; destroy's is an empty 204.
 *
 * It also adds `views: true` to the grid's schema by overriding
 * `getSchemaBody()`. A grid that overrides `getSchemaBody()` itself replaces
 * this one, so it must add the flag too: `[...parent::getSchemaBody(), 'views' => true]`
 * when it extends a grid, or the same key in its own array.
 *
 * @phpstan-require-extends DataGrid
 *
 * @phpstan-require-implements HasDatagridViews
 */
trait InteractsWithDatagridViews
{
    /**
     * The actor's views of this grid: the default first, then by name ignoring
     * case, with the id as the tie-break.
     */
    public function handleViews(Request $request): JsonResponse
    {
        $form = IndexDatagridViewRequest::forGrid($this, $request);

        $views = DatagridView::query()
            ->visibleTo($form->actor(), $form->sectionKey())
            ->where('grid_key', $form->gridKey())
            ->orderByDesc('is_default')
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get();

        return DatagridViewResource::collection($views)->response();
    }

    public function handleViewStore(Request $request): JsonResponse
    {
        $form = StoreDatagridViewRequest::forGrid($this, $request);

        $validated = $form->validated();

        $view = CreateDatagridViewAction::run(new CreateDatagridViewData(
            section: $form->sectionKey(),
            gridKey: $form->gridKey(),
            name: $validated['name'],
            actor: $form->actor(),
            filterSets: DatagridViewState::filterSets($validated['filter_sets'] ?? null),
            sorts: DatagridViewState::sorts($validated['sorts'] ?? null),
            visibility: DatagridViewState::visibility($validated['visibility'] ?? null),
            pageSize: isset($validated['page_size']) ? (int) $validated['page_size'] : null,
            isDefault: (bool) ($validated['is_default'] ?? false),
        ));

        return DatagridViewResource::make($view)->response()->setStatusCode(201);
    }

    public function handleViewUpdate(Request $request, string $view): JsonResponse
    {
        $form = UpdateDatagridViewRequest::forGrid($this, $request);

        $validated = $form->validated();

        $view = UpdateDatagridViewAction::run(new UpdateDatagridViewData(
            view: $form->datagridView(),
            name: $validated['name'] ?? null,
            filterSets: DatagridViewState::filterSets($validated['filter_sets'] ?? null),
            sorts: DatagridViewState::sorts($validated['sorts'] ?? null),
            visibility: DatagridViewState::visibility($validated['visibility'] ?? null),
            pageSize: isset($validated['page_size']) ? (int) $validated['page_size'] : null,
        ));

        return DatagridViewResource::make($view)->response();
    }

    /**
     * Deleting the default leaves the grid with no default; no other view is
     * promoted.
     */
    public function handleViewDestroy(Request $request, string $view): JsonResponse
    {
        $form = DeleteDatagridViewRequest::forGrid($this, $request);

        DeleteDatagridViewAction::run(new DeleteDatagridViewData($form->datagridView()));

        return response()->json(null, 204);
    }

    /**
     * Makes the view the actor's only default for this grid.
     */
    public function handleViewDefault(Request $request, string $view): JsonResponse
    {
        $form = DefaultDatagridViewRequest::forGrid($this, $request);

        $view = SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($form->datagridView()));

        return DatagridViewResource::make($view)->response();
    }

    public function handleViewClearDefault(Request $request, string $view): JsonResponse
    {
        $form = DefaultDatagridViewRequest::forGrid($this, $request);

        $view = ClearDefaultDatagridViewAction::run(new ClearDefaultDatagridViewData($form->datagridView()));

        return DatagridViewResource::make($view)->response();
    }

    /**
     * The grid's own schema body plus `views: true`, which tells the client
     * this grid keeps saved views.
     *
     * @return array<string, mixed>
     */
    protected function getSchemaBody(): array
    {
        return [
            ...parent::getSchemaBody(),
            'views' => true,
        ];
    }
}
