<?php

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
use Dashworthy\DatagridViews\Data\VisibilityData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\DatagridViews\Tests\Fixtures\Admin;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Dashworthy\Visualizations\Data\SortData;
use Dashworthy\Visualizations\Enums\SortOperator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

/**
 * @return list<int>
 */
function defaultIds(string $section, string $gridKey): array
{
    return DatagridView::query()->forGrid($section, $gridKey)->where('is_default', true)->orderBy('id')->pluck('id')->all();
}

// --- create ---

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

/*
 | The database's unique index is the last word on names. A name the request
 | validation could not see coming (a concurrent save, or a collation that
 | folds more than case) must still come back as a validation error on name,
 | never a 500.
 */
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

// --- update ---

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

// --- delete ---

test('delete removes the view', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->asDefault()->create();

    DeleteDatagridViewAction::run(new DeleteDatagridViewData($view));

    expect(DatagridView::query()->whereKey($view->id)->exists())->toBeFalse();
});

// --- default ---

test('set default leaves exactly one default for the owner\'s grid, and touches no other grid, section or owner', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();
    $target = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();
    $otherGrid = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'invoices')->asDefault()->create();
    $otherSection = DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'members')->asDefault()->create();
    $otherOwner = DatagridView::factory()->ownedBy(User::factory()->create())->forGrid('app', 'members')->asDefault()->create();

    $admin = Admin::factory()->create();
    $sameKeyOtherType = DatagridView::factory()->ownedBy($admin)->forGrid('app', 'members')->asDefault()->create();

    $result = SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($target));

    expect($result->is($target))->toBeTrue()
        ->and($target->fresh()->is_default)->toBeTrue()
        ->and($previous->fresh()->is_default)->toBeFalse()
        ->and($otherGrid->fresh()->is_default)->toBeTrue()
        ->and($otherSection->fresh()->is_default)->toBeTrue()
        ->and($otherOwner->fresh()->is_default)->toBeTrue()
        ->and($sameKeyOtherType->fresh()->is_default)->toBeTrue();
});

test('set default on the view that already is the default keeps it the default', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();

    SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($view));

    expect(defaultIds('app', 'members'))->toBe([$view->id]);
});

/*
 | Clearing the old default and setting the new one happen in one transaction:
 | if the second step fails, the first is rolled back and the owner keeps
 | their previous default rather than being left with none.
 */
test('set default is atomic: a failure while setting the new default keeps the old one', function () {
    $previous = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();
    $target = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();

    DatagridView::saving(function (DatagridView $view) use ($target): void {
        if ($view->is($target) && $view->is_default) {
            throw new RuntimeException('Write failed.');
        }
    });

    expect(fn () => SetDefaultDatagridViewAction::run(new SetDefaultDatagridViewData($target)))
        ->toThrow(RuntimeException::class, 'Write failed.');

    expect($previous->fresh()->is_default)->toBeTrue()
        ->and($target->fresh()->is_default)->toBeFalse();
})->after(function () {
    DatagridView::flushEventListeners();
});

test('clear default leaves the grid with no default', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();

    $result = ClearDefaultDatagridViewAction::run(new ClearDefaultDatagridViewData($view));

    expect($result->is_default)->toBeFalse()
        ->and(defaultIds('app', 'members'))->toBe([]);
});
