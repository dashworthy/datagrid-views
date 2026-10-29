<?php

use Dashworthy\DatagridViews\Actions\UpdateDatagridViewAction;
use Dashworthy\DatagridViews\Data\UpdateDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Dashworthy\Visualizations\Data\SortData;
use Dashworthy\Visualizations\Enums\SortOperator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

test('update changes only the fields it is given', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->withState()->create(['name' => 'Before']);
    $before = $view->fresh();

    UpdateDatagridViewAction::run(new UpdateDatagridViewData($view, sorts: [new SortData('column_Email', SortOperator::ASC)]));

    $after = $view->fresh();

    expect($after->sorts)->toBe([['field' => 'column_Email', 'sort_operator' => 'asc']])
        ->and($after->name)->toBe('Before')
        ->and($after->filter_sets)->toBe($before->filter_sets)
        ->and($after->visibility)->toBe($before->visibility)
        ->and($after->page_size)->toBe($before->page_size);
});

test('update writes empty lists as empty lists', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->withState()->create();

    UpdateDatagridViewAction::run(new UpdateDatagridViewData($view, filterSets: [], sorts: [], visibility: []));

    $after = $view->fresh();

    expect($after->filter_sets)->toBe([])
        ->and($after->sorts)->toBe([])
        ->and($after->visibility)->toBe([]);
});

test('update reports a name the database refuses as a validation error on name', function () {
    DatagridView::factory()->ownedBy($this->owner)->create(['name' => 'Taken']);
    $view = DatagridView::factory()->ownedBy($this->owner)->create(['name' => 'Mine']);

    expect(fn () => UpdateDatagridViewAction::run(new UpdateDatagridViewData($view, name: 'Taken')))
        ->toThrow(ValidationException::class);

    expect($view->fresh()->name)->toBe('Mine');
});
