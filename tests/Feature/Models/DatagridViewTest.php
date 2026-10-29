<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

test('saved grid state reads back exactly as it was written', function () {
    $state = [
        'filter_sets' => [
            [
                'filters' => [
                    ['field' => 'floating_filter_Created At', 'value' => '-30 days', 'filter_operator' => 'gte'],
                    ['field' => 'floating_filter_Created At', 'value' => '2026-03-31', 'filter_operator' => 'lte'],
                ],
                'filter_set_operator' => 'and',
            ],
            [
                'filters' => [
                    ['field' => 'column_Status', 'value' => ['active', 'invited'], 'filter_operator' => 'in'],
                ],
                'filter_set_operator' => 'or',
            ],
        ],
        'sorts' => [
            ['field' => 'column_Name', 'sort_operator' => 'desc'],
            ['field' => 'column_Email', 'sort_operator' => 'asc'],
        ],
        'visibility' => [
            ['field' => 'column_Email', 'is_hidden' => true],
            ['field' => 'column_Name', 'is_hidden' => false],
        ],
    ];

    $view = DatagridView::factory()->ownedBy($this->owner)->create([...$state, 'page_size' => 50]);

    $reloaded = DatagridView::query()->findOrFail($view->id);

    expect([
        'filter_sets' => $reloaded->filter_sets,
        'sorts' => $reloaded->sorts,
        'visibility' => $reloaded->visibility,
    ])->toBe($state)
        ->and($reloaded->page_size)->toBe(50)
        ->and($reloaded->is_default)->toBeFalse();
});

test('a view with no saved state reads back as nulls', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->create();

    $reloaded = DatagridView::query()->findOrFail($view->id);

    expect($reloaded->filter_sets)->toBeNull()
        ->and($reloaded->sorts)->toBeNull()
        ->and($reloaded->visibility)->toBeNull()
        ->and($reloaded->page_size)->toBeNull();
});

test('is_default is cast to a boolean', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->asDefault()->create();

    expect(DatagridView::query()->findOrFail($view->id)->is_default)->toBeTrue();
});

test('the fillable attributes are exactly the view\'s own settings, never its owner, place or default flag', function () {
    expect((new DatagridView)->getFillable())->toBe([
        'name',
        'filter_sets',
        'sorts',
        'visibility',
        'page_size',
    ]);
});

test('section, grid key and default flag cannot be mass-assigned', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();

    $view->update(['section' => 'admin', 'grid_key' => 'invoices', 'is_default' => true, 'name' => 'Renamed']);

    $fresh = $view->fresh();

    expect($fresh->section)->toBe('app')
        ->and($fresh->grid_key)->toBe('members')
        ->and($fresh->is_default)->toBeFalse()
        ->and($fresh->name)->toBe('Renamed');
});

test('owner resolves the polymorphic owner', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->create();

    expect($view->fresh()->owner)->toBeInstanceOf(User::class)
        ->and($view->fresh()->owner->is($this->owner))->toBeTrue();
});

test('forGrid narrows to one grid in one section', function () {
    $wanted = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'invoices')->create();
    DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'members')->create();

    expect(DatagridView::query()->forGrid('app', 'members')->pluck('id')->all())->toBe([$wanted->id]);
});

test('ownedBy narrows to one owner', function () {
    $other = User::factory()->create();
    $mine = DatagridView::factory()->ownedBy($this->owner)->create();
    DatagridView::factory()->ownedBy($other)->create();

    expect(DatagridView::query()->ownedBy($this->owner)->pluck('id')->all())->toBe([$mine->id]);
});

test('ownedBy matches the owner type as well as its key', function () {
    $mine = DatagridView::factory()->ownedBy($this->owner)->create();

    $sameKeyOtherType = DatagridView::factory()->ownedBy($this->owner)->create();
    $sameKeyOtherType->forceFill(['owner_type' => 'some-other-identity'])->save();

    expect(DatagridView::query()->ownedBy($this->owner)->pluck('id')->all())->toBe([$mine->id]);
});

test('the same name may be saved on two grids, in two sections, or by two owners', function () {
    $other = User::factory()->create();

    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create(['name' => 'Active']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'invoices')->create(['name' => 'Active']);
    DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'members')->create(['name' => 'Active']);
    DatagridView::factory()->ownedBy($other)->forGrid('app', 'members')->create(['name' => 'Active']);

    expect(DatagridView::query()->where('name', 'Active')->count())->toBe(4);
});

test('the database refuses a second view with the same name by the same owner on the same grid', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create(['name' => 'Active']);

    expect(fn () => DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create(['name' => 'Active']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the owner cannot be mass-assigned', function () {
    $view = new DatagridView(['owner_type' => 'some-other-identity', 'owner_id' => 9, 'name' => 'Active']);

    expect($view->owner_type)->toBeNull()
        ->and($view->owner_id)->toBeNull()
        ->and($view->name)->toBe('Active');
});

test('the factory fills a complete, valid view', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->withState()->create();

    expect($view->section)->toBeString()->not->toBeEmpty()
        ->and($view->grid_key)->toBeString()->not->toBeEmpty()
        ->and($view->name)->toBeString()->not->toBeEmpty()
        ->and($view->filter_sets)->toBeArray()
        ->and($view->sorts)->toBeArray()
        ->and($view->visibility)->toBeArray()
        ->and($view->page_size)->toBeInt();
});
