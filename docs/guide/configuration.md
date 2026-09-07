# Configuration guide

Start with package defaults and override only the behavior shared by most tables
in the application. A table class can override pagination, debounce, sticky
layout, and column interactions for one screen.

## Publish the config

```bash
php artisan vendor:publish --tag=inertia-table-config
```

The file is published to `config/inertia-table.php`.

## Pagination defaults

```php
'per_page' => 25,
'per_page_options' => [10, 25, 50, 100],
'pagination_type' => 'full',
```

Override these values for one table:

```php
use Musing\InertiaTable\PaginationType;

final class TopicsTable extends Table
{
    protected ?int $perPage = 50;
    protected ?array $perPageOptions = [25, 50, 100];
    protected ?PaginationType $paginationType = PaginationType::Cursor;
}
```

Read [pagination and URL state](/guide/pagination-and-url-state) before choosing
simple or cursor pagination.

## Interaction timing

`debounce` controls delayed table visits for global search and layout changes.
The default is 300 milliseconds. Text and numeric filter controls use the same
table-level debounce value.

## Column layout

```php
'sticky' => [
    'footer' => false,
    'backdrop_filter' => true,
],
'columns' => [
    'resizable' => true,
    'reorderable' => true,
],
```

Columns still opt in with `resizable()`, `reorderable()`, or `stickable()`. The
global switches can disable interactions across the application without
changing every table definition.

## Managed route paths

Actions, exports, and Saved Views use package-owned signed routes. Their path
prefixes are configurable:

```php
'action_path' => '_inertia-table/actions',
'export_path' => '_inertia-table/exports',
'view_path' => '_inertia-table/views',
```

Change a path before publishing routes in production. Existing signed URLs are
not portable across path changes.

## Queue defaults

Queued actions and exports have separate default groups because their storage
and retention requirements differ. See [queues](/features/queues) and
[exports](/features/exports) for operational setup.

For every key and default value, use the [configuration reference](/reference/configuration).
