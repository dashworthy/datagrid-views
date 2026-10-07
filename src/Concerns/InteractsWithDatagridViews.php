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
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\Visualizations\Contracts\HandlesDataGridViews;
use Dashworthy\Visualizations\DataGrids\Abstracts\DataGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
 * The signatures are exactly those {@see HandlesDataGridViews} declares: each
 * hook takes only the request. `{view}` is read from the route by name (never
 * by position, which behind a host prefix or domain with its own parameter,
 * a `{tenant}`, would be that parameter) and looked up as a scalar id, never
 * bound to a model. Destroy answers an empty 204; the default hooks answer
 * the view as JSON, though they declare the interface's Symfony Response.
 *
 * It also adds `views: true` to the grid's schema by overriding the static
 * `schema()`, so a grid may override `getSchemaBody()` freely. A grid that
 * overrides `schema()` itself must keep the flag:
 * `[...parent::schema(), ...]` does.
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

        $views = $form->gridViews()
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

    public function handleViewUpdate(Request $request): JsonResponse
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
    public function handleViewDestroy(Request $request): Response
    {
        $form = DeleteDatagridViewRequest::forGrid($this, $request);

        DeleteDatagridViewAction::run(new DeleteDatagridViewData($form->datagridView()));

        return response()->noContent();
    }

    /**
     * Makes the view the actor's only default for this grid.
     */
    public function handleViewDefault(Request $request): Response
    {
        $form = DefaultDatagridViewRequest::forGrid($this, $request);

        $view = SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($form->datagridView()));

        return DatagridViewResource::make($view)->response();
    }

    public function handleViewClearDefault(Request $request): Response
    {
        $form = DefaultDatagridViewRequest::forGrid($this, $request);

        $view = ClearDefaultDatagridViewAction::run(new ClearDefaultDatagridViewData($form->datagridView()));

        return DatagridViewResource::make($view)->response();
    }

    /**
     * The grid's schema plus `views: true`, which tells the client this grid
     * keeps saved views.
     *
     * Added to `schema()` rather than to `getSchemaBody()`: grids customise
     * their schema body, and one that overrode it would silently drop a flag
     * added there. A grid that overrides `schema()` itself must keep the flag.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            ...parent::schema(),
            'views' => true,
        ];
    }
}
