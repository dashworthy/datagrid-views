<?php

namespace Dashworthy\DatagridViews\Http\Requests;

use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Dashworthy\Visualizations\DataGrids\Abstracts\DataGrid;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use LogicException;

/**
 * A request to one of a grid's view routes, which `Route::dataGrid()` mounts
 * from the hooks of {@see InteractsWithDatagridViews}.
 *
 * Everything that scopes a view comes from the server, never the payload: the
 * grid is the one whose hook is running, the section is the one the grid declares,
 * the grid key is the grid's visualization key (the `visualization_key` its
 * schema sends), and the actor is the authenticated user.
 */
abstract class DatagridViewRequest extends FormRequest
{
    private (DataGrid&HasDatagridViews)|null $grid = null;

    private ?DatagridView $resolvedView = null;

    /**
     * Builds the request for $grid's hook from the request the hook received,
     * then authorizes and validates it as Laravel does for a type-hinted form
     * request: 404 for a `{view}` out of scope, 403 when refused, 422 when the
     * body is invalid.
     *
     * The hooks receive a plain request (that is the signature
     * `Route::dataGrid()` calls) and build their form request here, which
     * also hands it the grid directly instead of reading it off the route.
     */
    public static function forGrid(DataGrid&HasDatagridViews $grid, Request $request): static
    {
        $formRequest = static::createFrom($request);
        $formRequest->grid = $grid;
        $formRequest->setContainer(app())->setRedirector(app(Redirector::class));
        $formRequest->validateResolved();

        return $formRequest;
    }

    /**
     * The grid whose view route is handling this request.
     */
    public function grid(): DataGrid&HasDatagridViews
    {
        return $this->grid ?? throw new LogicException(static::class.' is built with forGrid() from a grid\'s view hook, never resolved on its own.');
    }

    /**
     * The key of the section the grid keeps its views in. Throws when that
     * section is not listed in config or shares its key with another listed
     * enum ({@see DatagridViewSections::keyOf()}).
     */
    public function sectionKey(): string
    {
        return app(DatagridViewSections::class)->keyOf($this->grid()->getDatagridViewSection());
    }

    /**
     * The key the grid's views are stored under: its visualization key.
     */
    public function gridKey(): string
    {
        return $this->grid()->getVisualizationKey();
    }

    /**
     * The authenticated user. A host that mounts the grid without an auth
     * middleware still gets a 401 here, never a view filed under nobody.
     *
     * @throws AuthenticationException
     */
    public function actor(): Authenticatable&Model
    {
        $user = $this->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * The `{view}` the route names, looked up only among the actor's visible
     * views of this grid in this section.
     *
     * Another owner's view, another section's or another grid's, an unknown id
     * and anything that is not an id all 404 alike. The id is read from the
     * route bag by name, never from the hook's `$view` argument: Laravel
     * passes route parameters to a controller method positionally, so behind
     * a host prefix or domain carrying its own parameter (a `{tenant}`) that
     * argument holds the tenant, not the view.
     *
     * @throws ModelNotFoundException<DatagridView>
     */
    public function datagridView(): DatagridView
    {
        return $this->resolvedView ??= $this->findDatagridView();
    }

    /**
     * Whether the actor may see the grid itself. A grid that names a
     * permission (`getPermissionName()`, which visualizations checks before
     * answering its data and schema) holds its views behind the same gate.
     */
    protected function actorMaySeeGrid(): bool
    {
        $grid = $this->grid();

        return ! method_exists($grid, 'getPermissionName')
            || $this->actor()->can($grid->getPermissionName());
    }

    private function findDatagridView(): DatagridView
    {
        $id = $this->route('view');

        if (! is_string($id) || ! ctype_digit($id)) {
            throw (new ModelNotFoundException)->setModel(DatagridView::class);
        }

        return DatagridView::query()
            ->visibleTo($this->actor(), $this->sectionKey())
            ->where('grid_key', $this->gridKey())
            ->findOrFail((int) $id);
    }
}
