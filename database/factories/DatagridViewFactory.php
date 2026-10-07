<?php

namespace Dashworthy\DatagridViews\Database\Factories;

use Dashworthy\DatagridViews\Models\DatagridView;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * A view has no owner of its own: the package knows no identity model. Give it
 * one with `ownedBy()` (or `for($owner, 'owner')`) before creating it.
 *
 * @extends Factory<DatagridView>
 */
class DatagridViewFactory extends Factory
{
    protected $model = DatagridView::class;

    public function definition(): array
    {
        return [
            'section' => 'app',
            'grid_key' => 'members',
            'name' => fake()->unique()->words(3, true),
            'filter_sets' => null,
            'sorts' => null,
            'visibility' => null,
            'page_size' => null,
            'is_default' => false,
        ];
    }

    public function ownedBy(Model $owner): static
    {
        return $this->for($owner, 'owner');
    }

    public function forGrid(string $section, string $gridKey): static
    {
        return $this->state(['section' => $section, 'grid_key' => $gridKey]);
    }

    public function asDefault(): static
    {
        return $this->state(['is_default' => true]);
    }

    /**
     * Fill every piece of saved grid state, in the wire shapes the client
     * sends.
     */
    public function withState(): static
    {
        return $this->state([
            'filter_sets' => [[
                'filters' => [
                    ['field' => 'floating_filter_Created At', 'value' => '-30 days', 'filter_operator' => 'gte'],
                ],
                'filter_set_operator' => 'and',
            ]],
            'sorts' => [['field' => 'column_Name', 'sort_operator' => 'asc']],
            'visibility' => [['field' => 'column_Email', 'is_hidden' => true]],
            'page_size' => 25,
        ]);
    }
}
