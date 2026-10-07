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

## Giving a grid saved views

Views are opted into per grid. Implement `Dashworthy\DatagridViews\Contracts\HasDatagridViews` on a visualizations `DataGrid`, use the `Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews` trait, and return the section the grid's views live in:

```php
use Dashworthy\DatagridViews\Concerns\InteractsWithDatagridViews;
use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\HasDatagridViews;
use Dashworthy\Visualizations\DataGrids\Abstracts\DataGrid;

class MembersDataGrid extends DataGrid implements HasDatagridViews
{
    use InteractsWithDatagridViews;

    public function getDatagridViewSection(): DefinesDatagridViewSection
    {
        return AppSection::App;
    }

    // getColumns(), getQuery(), ...
}
```

Then mount the grid as usual, inside a group that runs authentication:

```php
Route::middleware('auth')->group(function () {
    Route::dataGrid(MembersDataGrid::class);
});
```

`Route::dataGrid()` (from `dashworthy/visualizations`) mounts the grid's view routes because the grid has the trait's hooks. A grid without the trait gets none. The package registers no routes of its own.

The views are stored under the grid's section and its visualization key (the `visualization_key` its schema sends, e.g. `grids.members`). The client never sends either one. They come from the grid handling the request, so a view saved on one grid never shows on another.

A request to a grid whose section is not listed in config, or whose key another listed enum also uses, throws instead of borrowing another section's rules. If the grid names a permission with `getPermissionName()`, its views are behind the same gate as its data and schema. A guest gets a 401 even if the host forgot the auth middleware.

### Schema flag

The trait adds `views: true` to the grid's schema by overriding `getSchemaBody()`, so the client knows to offer views. If a grid overrides `getSchemaBody()` itself, its method replaces the trait's, and it must add the flag too:

```php
protected function getSchemaBody(): array
{
    return [...parent::getSchemaBody(), 'my_key' => '...', 'views' => true];
}
```

## Routes

For a grid with route name `{name}` and path `{path}` (for example `grids.members` and `grids/members`):

| Name | Method and URI | Hook | Body | Answer |
| --- | --- | --- | --- | --- |
| `{name}.views` | `GET {path}/views` | `handleViews` | | `{data: View[]}`: the actor's views of this grid, the default first, then by name |
| `{name}.views.store` | `POST {path}/views` | `handleViewStore` | `name`, optional `filter_sets`, `sorts`, `visibility`, `page_size`, `is_default` | 201 `{data: View}` |
| `{name}.views.update` | `PATCH {path}/views/{view}` | `handleViewUpdate` | any of `name`, `filter_sets`, `sorts`, `visibility`, `page_size` | `{data: View}` |
| `{name}.views.destroy` | `DELETE {path}/views/{view}` | `handleViewDestroy` | | 204 |
| `{name}.views.default` | `PUT {path}/views/{view}/default` | `handleViewDefault` | | `{data: View}`, now the grid's only default |
| `{name}.views.clear-default` | `DELETE {path}/views/{view}/default` | `handleViewClearDefault` | | `{data: View}` with `is_default: false` |

A `View` is `{id, grid_key, name, filter_sets, sorts, visibility, page_size, is_default, updated_at}`. The state fields use the same wire shapes as `dashboard_widgets`.

- A view name must be unique, ignoring case, among the owner's views of the grid in that section. A clash is a 422.
- A `{view}` belonging to another owner, another section or another grid answers 404, the same as an unknown id.
- An actor refused by the section's `canUse()` gets a 403.
- `{view}` is read from the route by name and is never model-bound. That keeps it safe when the grid is mounted under a prefix or domain with its own parameter, such as `{tenant}`.

### Null versus empty state

`filter_sets`, `sorts` and `visibility` distinguish `null` from `[]`:

- `null` (or leaving the field out when saving a new view) means the view does not save that part of the grid; the page's own setup applies when the view is opened. The same goes for `page_size`.
- `[]` means "explicitly none": no filters, no sorts, nothing hidden.

`PATCH` refuses `null` for every field with a 422. Leave a field out to keep it as it is, or send `[]` to save "none".

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
