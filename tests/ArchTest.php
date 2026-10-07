<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Dashworthy\DatagridViews\Contracts')
    ->toBeInterfaces();

arch('actions are foundry actions that declare handle')
    ->expect('Dashworthy\DatagridViews\Actions')
    ->toExtend('Dashworthy\Foundry\Actions\Action')
    ->toHaveMethod('handle');
