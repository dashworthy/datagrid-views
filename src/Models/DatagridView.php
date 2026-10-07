<?php

namespace Dashworthy\DatagridViews\Models;

use Dashworthy\DatagridViews\Database\Factories\DatagridViewFactory;
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
 * `dashboard_widgets` stores, read back exactly as written.
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
     * owner_type / owner_id are deliberately absent: ownership is assigned
     * structurally via owner()->associate() from the section's access rule,
     * never mass-assigned from request input.
     */
    protected $fillable = [
        'section',
        'grid_key',
        'name',
        'filter_sets',
        'sorts',
        'visibility',
        'page_size',
        'is_default',
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

    protected static function newFactory(): DatagridViewFactory
    {
        return DatagridViewFactory::new();
    }
}
