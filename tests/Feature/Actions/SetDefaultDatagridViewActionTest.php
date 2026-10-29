<?php

use Dashworthy\DatagridViews\Actions\SetDefaultDatagridViewAction;
use Dashworthy\DatagridViews\Data\SetDefaultDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\Admin;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

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
