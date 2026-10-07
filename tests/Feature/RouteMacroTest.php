<?php

use Dashworthy\DatagridViews\Tests\Fixtures\CollidingSection;
use Dashworthy\DatagridViews\Tests\Fixtures\TestSection;
use Illuminate\Support\Facades\Route;

test('the router offers a datagridViewSection macro', function () {
    expect(Route::hasMacro('datagridViewSection'))->toBeTrue();
});

test('mounting a configured section loads without error', function () {
    expect(fn () => Route::datagridViewSection(TestSection::App))->not->toThrow(Throwable::class);
});

test('mounting a section that is not listed in config fails while routes load', function () {
    config()->set('datagrid-views.sections', []);

    expect(fn () => Route::datagridViewSection(TestSection::App))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section [app] is not listed in config('datagrid-views.sections').");
});

test('mounting a section whose key another configured enum also claims fails while routes load', function () {
    config()->set('datagrid-views.sections', [CollidingSection::class, TestSection::class]);

    expect(fn () => Route::datagridViewSection(TestSection::App))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section key [app] is registered by more than one enum in config('datagrid-views.sections').");
});

test('a section whose key is shared is refused even when its own enum is listed first', function () {
    config()->set('datagrid-views.sections', [TestSection::class, CollidingSection::class]);

    expect(fn () => Route::datagridViewSection(TestSection::App))
        ->toThrow(InvalidArgumentException::class, 'registered by more than one enum');
});

test('mounting an unlisted enum whose key a listed enum uses fails rather than borrowing its rules', function () {
    config()->set('datagrid-views.sections', [TestSection::class]);

    expect(fn () => Route::datagridViewSection(CollidingSection::App))
        ->toThrow(InvalidArgumentException::class, "Datagrid view section [app] is not listed in config('datagrid-views.sections').");
});
