<?php

namespace Dashworthy\DatagridViews\Models;

use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;
use Dashworthy\DatagridViews\Database\Factories\DatagridViewFactory;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One owner's saved view of one data grid: which columns are hidden, how rows
 * are sorted and filtered, and how many show per page.
 *
 * `filter_sets`, `sorts` and `visibility` hold the same snake_case wire shapes
 * `dashboard_widgets` stores. They read back with the same values; MySQL's
 * native JSON type may reorder object keys, so compare them as structures,
 * never as serialized strings.
 *
 * @property int $id
 * @property string $section
 * @property string $grid_key
 * @property string $owner_type
 * @property int $owner_id
 * @property string $name
 * @property array<int, array{filters: array<int, array{field: string, value: mixed, filter_operator: string}>, filter_set_operator: string}>|null $filter_sets
 * @property array<int, array{field: string, sort_operator: string}>|null $sorts
 * @property array<int, array{field: string, is_hidden: bool}>|null $visibility
 * @property int|null $page_size
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Model $owner
 */
class DatagridView extends Model
{
    /** @use HasFactory<DatagridViewFactory> */
    use HasFactory;

    /**
     * Only the settings a user edits. The owner, section and grid decide whose
     * scope a view lives in, and the default flag is held to one per grid by
     * SetDefaultDatagridViewAction's lock, so none of them can be filled from
     * input: the actions set them as properties.
     */
    protected $fillable = [
        'name',
        'filter_sets',
        'sorts',
        'visibility',
        'page_size',
    ];

    protected $casts = [
        'filter_sets' => 'array',
        'sorts' => 'array',
        'visibility' => 'array',
        'page_size' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Narrow the query to the views of one grid in one section.
     *
     * @param  Builder<DatagridView>  $query
     */
    public function scopeForGrid(Builder $query, string $section, string $gridKey): void
    {
        $query->where('section', $section)->where('grid_key', $gridKey);
    }

    /**
     * Narrow the query to one owner's views, matching both morph columns so
     * two identity types that share a key stay apart.
     *
     * @param  Builder<DatagridView>  $query
     */
    public function scopeOwnedBy(Builder $query, Model $owner): void
    {
        $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    /**
     * Narrow the query to the views $actor may see in one section: that
     * section's rows only, further narrowed by the section's access rule
     * ({@see GovernsDatagridViewAccess::scopeVisible()}).
     *
     * The rule's constraints are wrapped in their own group, so an `orWhere`
     * inside it can never escape the section constraint.
     *
     * @param  Builder<DatagridView>  $query
     */
    public function scopeVisibleTo(Builder $query, Authenticatable&Model $actor, string $section): void
    {
        $access = app(DatagridViewSections::class)->find($section)->datagridViewAccess();

        $query->where('section', $section)
            ->where(function (Builder $query) use ($access, $actor): void {
                $access->scopeVisible($query, $actor);
            });
    }

    /**
     * Narrow the query to every view sharing $view's section, grid and owner,
     * $view included: the set within which a name is unique and at most one
     * view is the default.
     *
     * @param  Builder<DatagridView>  $query
     */
    public function scopeSiblingsOf(Builder $query, DatagridView $view): void
    {
        $query->forGrid($view->section, $view->grid_key)
            ->where('owner_type', $view->owner_type)
            ->where('owner_id', $view->owner_id);
    }

    protected static function newFactory(): DatagridViewFactory
    {
        return DatagridViewFactory::new();
    }
}
