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

The group must run authentication and `SubstituteBindings` (the `web` and `api` groups do).

## Endpoints

Each mounted section `{key}` gets a JSON API, named `{key}.datagrid-views.*`:

| Name | Method and URI | Body | Answer |
| --- | --- | --- | --- |
| `index` | `GET {key}/datagrid-views?grid={grid_key}` | | `{data: View[]}`: the actor's views of that grid, the default first, then by name |
| `store` | `POST {key}/datagrid-views` | `grid_key`, `name`, optional `filter_sets`, `sorts`, `visibility`, `page_size`, `is_default` | 201 `{data: View}` |
| `show` | `GET {key}/datagrid-views/{datagridView}` | | `{data: View}` |
| `update` | `PATCH {key}/datagrid-views/{datagridView}` | any of `name`, `filter_sets`, `sorts`, `visibility`, `page_size` | `{data: View}` |
| `destroy` | `DELETE {key}/datagrid-views/{datagridView}` | | 204 |
| `default` | `PUT {key}/datagrid-views/{datagridView}/default` | | `{data: View}`, now the grid's only default |
| `clear-default` | `DELETE {key}/datagrid-views/{datagridView}/default` | | `{data: View}` with `is_default: false` |

A `View` is `{id, grid_key, name, filter_sets, sorts, visibility, page_size, is_default, updated_at}`. The state fields use the same wire shapes as `dashboard_widgets`. A view of another owner or another section answers 404, and an actor the section's `canUse()` refuses gets 403.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
