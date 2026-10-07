# Datagrid Views

Saved, private views of a data grid: which columns are hidden, how rows are sorted and filtered, and how many show per page. Each view belongs to exactly one owner, and views are kept per **section** of the host application, so an admin area can keep them in the central database and a tenant area in the tenant's.

## Installation

```bash
composer require dashworthy/datagrid-views
php artisan vendor:publish --tag="datagrid-views-config"
php artisan vendor:publish --tag="datagrid-views-migrations"
php artisan migrate
```

Publish the migration once per connection that keeps views (for example the central and the tenant migration paths).

## Sections

A section is a case of a host enum implementing `Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection`. It names its key and returns the section's access rules, a `Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess`:

- `ownerFor($actor)`: the model that owns a view the actor saves (never null).
- `canUse($actor)`: whether the actor may have views in this section at all. Must fail closed.
- `scopeVisible($query, $actor)`: narrow a query to the actor's own views. Must fail closed.

List every section enum in `config/datagrid-views.php`:

```php
'sections' => [
    App\Enums\AppSection::class,
],
```

Then mount each section inside the host's own middleware group:

```php
Route::middleware(['auth'])->group(function () {
    Route::datagridViewSection(AppSection::Admin);
});
```

Mounting a section that is not listed in config, or whose key another listed enum also uses, throws while routes load.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
