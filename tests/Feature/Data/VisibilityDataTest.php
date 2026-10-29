<?php

use Dashworthy\DatagridViews\Data\VisibilityData;

test('it serialises to the wire shape', function () {
    expect((new VisibilityData('column_Email', true))->toArray())
        ->toBe(['field' => 'column_Email', 'is_hidden' => true]);
});
