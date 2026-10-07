<?php

use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Dashworthy\DatagridViews\Tests\Fixtures\OwnerOnlyAccess;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;

test('find returns the configured section case for a stored key', function () {
    expect(app(DatagridViewSections::class)->find('admin'))->toBe(TestSection::Admin)
        ->and(app(DatagridViewSections::class)->find('app'))->toBe(TestSection::App);
});

test('the found section hands back its access rules', function () {
    expect(app(DatagridViewSections::class)->find('app')->datagridViewAccess())
        ->toBeInstanceOf(OwnerOnlyAccess::class);
});

test('find throws for a key no configured section uses', function () {
    expect(fn () => app(DatagridViewSections::class)->find('unregistered'))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section [unregistered] is not listed in config('datagrid-views.sections').");
});

test('find throws when no sections are configured at all', function () {
    config()->set('datagrid-views.sections', []);

    expect(fn () => app(DatagridViewSections::class)->find('app'))
        ->toThrow(InvalidArgumentException::class);
});

test('the package config ships with no sections, so a host must list its own', function () {
    $shipped = require __DIR__.'/../../config/datagrid-views.php';

    expect($shipped)->toBe(['sections' => []]);
});
