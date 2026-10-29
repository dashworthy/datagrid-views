<?php

use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Dashworthy\DatagridViews\Tests\Fixtures\CollidingSection;
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
    $shipped = require __DIR__.'/../../../config/datagrid-views.php';

    expect($shipped)->toBe(['sections' => []]);
});

test('keyOf returns the key of a listed section', function () {
    expect(app(DatagridViewSections::class)->keyOf(TestSection::App))->toBe('app')
        ->and(app(DatagridViewSections::class)->keyOf(TestSection::Admin))->toBe('admin');
});

test('keyOf throws for a section that is not listed in config', function () {
    config()->set('datagrid-views.sections', []);

    expect(fn () => app(DatagridViewSections::class)->keyOf(TestSection::App))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section [app] is not listed in config('datagrid-views.sections').");
});

test('keyOf throws for a section whose key another configured enum also claims, whichever is listed first', function (array $sections) {
    config()->set('datagrid-views.sections', $sections);

    expect(fn () => app(DatagridViewSections::class)->keyOf(TestSection::App))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section key [app] is registered by more than one enum in config('datagrid-views.sections').");
})->with([
    'colliding enum first' => [[CollidingSection::class, TestSection::class]],
    'own enum first' => [[TestSection::class, CollidingSection::class]],
]);

test('keyOf throws for an unlisted enum whose key a listed enum uses, rather than borrowing its rules', function () {
    config()->set('datagrid-views.sections', [TestSection::class]);

    expect(fn () => app(DatagridViewSections::class)->keyOf(CollidingSection::App))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section [app] is not listed in config('datagrid-views.sections').");
});
