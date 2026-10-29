<?php

use Dashworthy\DatagridViews\Data\VisibilityData;
use Dashworthy\DatagridViews\Support\DatagridViewState;
use Dashworthy\Visualizations\Data\FilterSetData;
use Dashworthy\Visualizations\Data\SortData;

test('null state stays null', function () {
    expect(DatagridViewState::filterSets(null))->toBeNull()
        ->and(DatagridViewState::sorts(null))->toBeNull()
        ->and(DatagridViewState::visibility(null))->toBeNull()
        ->and(DatagridViewState::toWire(null))->toBeNull();
});

test('wire state hydrates into data objects', function () {
    $state = gridState();

    expect(DatagridViewState::filterSets($state['filter_sets']))->toHaveCount(2)->each->toBeInstanceOf(FilterSetData::class)
        ->and(DatagridViewState::sorts($state['sorts']))->toHaveCount(2)->each->toBeInstanceOf(SortData::class)
        ->and(DatagridViewState::visibility($state['visibility']))->toHaveCount(2)->each->toBeInstanceOf(VisibilityData::class);
});

test('sorts and visibility round-trip back to the wire shape', function () {
    $state = gridState();

    expect(DatagridViewState::toWire(DatagridViewState::sorts($state['sorts'])))->toBe($state['sorts'])
        ->and(DatagridViewState::toWire(DatagridViewState::visibility($state['visibility'])))->toBe($state['visibility']);
});
