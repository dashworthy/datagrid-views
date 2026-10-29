<?php

use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\Admin;
use Dashworthy\DatagridViews\Tests\Fixtures\ScriptedAccess;
use Dashworthy\DatagridViews\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->create();
});

test('the owner may view, update and delete their view', function (string $ability) {
    expect(Gate::forUser($this->owner)->allows($ability, $this->view))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('another owner may not view, update or delete the view', function (string $ability) {
    expect(Gate::forUser(User::factory()->create())->allows($ability, $this->view))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('an identity of another type sharing the owner\'s key may not view, update or delete the view', function (string $ability) {
    $admin = Admin::factory()->create();

    expect($admin->getKey())->toBe($this->owner->getKey())
        ->and(Gate::forUser($admin)->allows($ability, $this->view))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('an actor the section shuts out may not view, update or delete even their own view', function (string $ability) {
    $scripted = DatagridView::factory()->ownedBy($this->owner)->forGrid('scripted', 'members')->create();

    expect(Gate::forUser($this->owner)->allows($ability, $scripted))->toBeTrue();

    ScriptedAccess::$canUse = false;

    expect(Gate::forUser($this->owner)->allows($ability, $scripted))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('listing and creating in a section follow the section\'s canUse', function (string $ability) {
    expect(Gate::forUser($this->owner)->allows($ability, [DatagridView::class, 'scripted']))->toBeTrue();

    ScriptedAccess::$canUse = false;

    expect(Gate::forUser($this->owner)->allows($ability, [DatagridView::class, 'scripted']))->toBeFalse();
})->with(['viewAny', 'create']);

test('visibleTo narrows to the actor\'s views in that one section', function () {
    DatagridView::factory()->ownedBy($this->owner)->forGrid('admin', 'members')->create();
    DatagridView::factory()->ownedBy(User::factory()->create())->forGrid('app', 'members')->create();

    expect(DatagridView::query()->visibleTo($this->owner, 'app')->pluck('id')->all())->toBe([$this->view->id]);
});
