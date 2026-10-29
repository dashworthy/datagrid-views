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

arch('every class keeps to an approved directory')
    ->expect('Dashworthy\DatagridViews')
    ->toUseApprovedDirectories(
        [
            'Actions' => 'Dashworthy\Foundry\Actions\Action',
            'Concerns',
            'Contracts',
            'Data',
            'Http',
            'Models' => 'Illuminate\Database\Eloquent\Model',
            'Policies',
            'Support',
            'Database',
        ],
        beneath: 'Dashworthy\DatagridViews',
    )
    ->ignoring([
        'Dashworthy\DatagridViews\Tests',
        'Dashworthy\DatagridViews\DatagridViewsServiceProvider',
    ]);

arch('every class is named for the directory it sits in')
    ->expect('Dashworthy\DatagridViews')
    ->toUseDirectorySuffixes(
        [
            'Actions' => 'Action',
            'Data' => 'Data',
            'Policies' => 'Policy',
            'Requests' => 'Request',
            'Resources' => 'Resource',
        ],
        beneath: 'Dashworthy\DatagridViews',
    )
    ->ignoring('Dashworthy\DatagridViews\Tests');

arch('every class has a mirrored test')
    ->expect('Dashworthy\DatagridViews')
    ->toHaveTests([
        __DIR__.'/../src' => __DIR__.'/Feature',
    ], exempt: [
        'Dashworthy\Foundry\Data\Data',
    ])
    ->ignoring('Dashworthy\DatagridViews\Tests');
