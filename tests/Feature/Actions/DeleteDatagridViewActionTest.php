<?php

use Dashworthy\DatagridViews\Actions\DeleteDatagridViewAction;
use Dashworthy\DatagridViews\Data\DeleteDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

test('delete removes the view', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->asDefault()->create();

    DeleteDatagridViewAction::run(new DeleteDatagridViewData($view));

    expect(DatagridView::query()->whereKey($view->id)->exists())->toBeFalse();
});
