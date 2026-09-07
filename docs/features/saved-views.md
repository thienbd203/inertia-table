# Saved Views

Saved Views persist normalized table preferences such as sort, filters, column
layout, and page size. They are opt-in and scoped to the authenticated Laravel
user by default.

## Install the migration

```bash
php artisan vendor:publish --tag=inertia-table-migrations
php artisan migrate
```

## Enable views

```php
use Musing\InertiaTable\Views;

public function views(): ?Views
{
    return Views::make();
}
```

The toolbar now exposes the operations allowed by the definition: switch,
create, update, rename, delete, set default, and share.

## Stored state

A view stores:

- sort;
- enabled filters and normalized values;
- column visibility, order, widths, and pinned columns;
- page size;
- search only when `includeSearch()` is enabled.

Current page, cursor, and selection are not persisted.

## Scope views

```php
public function views(): ?Views
{
    return Views::make()
        ->includeSearch()
        ->scopeTableName()
        ->attributes(fn () => ['tenant_id' => tenant()->id]);
}
```

`attributes()` separates views by application context such as tenant or
workspace. `scopeTableName()` separates named instances of the same PHP table
class. Use `scopeUser(false)` for application-wide views, `userResolver()` for a
custom identity source, and `modelClass()` for a `TableView` subclass.

## Authorization

Fine-grained policies are available through:

- `authorizeCreate()`
- `authorizeUpdate()`
- `authorizeDelete()`
- `authorizeShare()`
- `authorizeDefault()`

The signed CRUD routes recheck their definition and authorization on every
request.

## State precedence

State is resolved in this order:

1. explicit URL values;
2. selected view;
3. user's default view;
4. table defaults.

Stored state is normalized against current columns, filters, and allowed page
sizes when read. Removing a capability from a table therefore prevents an old
view from restoring it.

## Concurrent edits

CRUD uses a `lock_version`. Updating or deleting a stale version is rejected
instead of overwriting a newer change. The Vue renderer keeps live dirty-state
feedback by comparing persistable state with the selected view.

## Custom renderer

```ts
import { useTable, useViews } from "@musing/inertia-table-vue";

const table = useTable(() => props.topics);
const views = useViews(table);
```

See [multiple tables](/guide/multiple-tables) when the same table class appears
more than once on a page.
