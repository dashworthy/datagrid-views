<?php

use Dashworthy\DatagridViews\Actions\ClearDefaultDatagridViewAction;
use Dashworthy\DatagridViews\Data\ClearDefaultDatagridViewData;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Tests\Fixtures\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

test('clear default leaves the grid with no default', function () {
    $view = DatagridView::factory()->ownedBy($this->owner)->forGrid('app', 'members')->asDefault()->create();

    $result = ClearDefaultDatagridViewAction::run(new ClearDefaultDatagridViewData($view));

    expect($result->is_default)->toBeFalse()
        ->and(defaultIds('app', 'members'))->toBe([]);
});
