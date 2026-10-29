<?php

use Dashworthy\DatagridViews\Actions\CreateDatagridViewAction;
use Dashworthy\DatagridViews\Data\CreateDatagridViewData;
use Dashworthy\DatagridViews\Data\VisibilityData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Dashworthy\Visualizations\Data\SortData;
use Dashworthy\Visualizations\Enums\SortOperator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

test('create owns the view by the section\'s owner for the actor and stores state in wire shape', function () {
    $view = CreateDatagridViewAction::run(new CreateDatagridViewData(
        section: 'app',
        gridKey: 'members',
        name: 'Recent',
        actor: $this->owner,
        filterSets: DatagridViewState::filterSets(gridState()['filter_sets']),
        sorts: [new SortData('column_Name', SortOperator::DESC)],
        visibility: [new VisibilityData('column_Email', true)],
        pageSize: 50,
    ));

    $stored = DatagridView::query()->findOrFail($view->id);

    expect($stored->owner_type)->toBe($this->owner->getMorphClass())
        ->and($stored->owner_id)->toBe($this->owner->getKey())
        ->and($stored->section)->toBe('app')
        ->and($stored->grid_key)->toBe('members')
        ->and($stored->filter_sets)->toBe(gridState()['filter_sets'])
        ->and($stored->sorts)->toBe([['field' => 'column_Name', 'sort_operator' => 'desc']])
        ->and($stored->visibility)->toBe([['field' => 'column_Email', 'is_hidden' => true]])
        ->and($stored->page_size)->toBe(50)
        ->and($stored->is_default)->toBeFalse();
});

test('create with is_default clears the owner\'s previous default for that grid only', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();
    $otherGrid = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'invoices')->asDefault()->create();
    $otherSection = DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'members')->asDefault()->create();
    $otherOwner = DatagridView::factory()->ownedBy(User::factory()->create())->forGrid('app', 'members')->asDefault()->create();

    $view = CreateDatagridViewAction::run(new CreateDatagridViewData('app', 'members', 'New', $this->owner, isDefault: true));

    expect($view->is_default)->toBeTrue()
        ->and($previous->fresh()->is_default)->toBeFalse()
        ->and($otherGrid->fresh()->is_default)->toBeTrue()
        ->and($otherSection->fresh()->is_default)->toBeTrue()
        ->and($otherOwner->fresh()->is_default)->toBeTrue()
        ->and(defaultIds('app', 'members'))->toBe([$otherOwner->id, $view->id]);
});

test('create reports a name the database refuses as a validation error on name', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create(['name' => 'Taken']);

    expect(fn () => CreateDatagridViewAction::run(new CreateDatagridViewData('app', 'members', 'Taken', $this->owner)))
        ->toThrow(function (ValidationException $e) {
            expect($e->errors())->toHaveKey('name');
        });

    expect(DatagridView::query()->forGrid('app', 'members')->count())->toBe(1);
});

test('create with is_default changes nothing when the name is refused', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create(['name' => 'Taken']);

    expect(fn () => CreateDatagridViewAction::run(new CreateDatagridViewData('app', 'members', 'Taken', $this->owner, isDefault: true)))
        ->toThrow(ValidationException::class);

    expect($previous->fresh()->is_default)->toBeTrue();
});
