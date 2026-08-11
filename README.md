# Toolbelt Inertia Table

[![Tests](https://github.com/thienbd203/inertia-table/actions/workflows/run-tests.yml/badge.svg)](https://github.com/thienbd203/inertia-table/actions/workflows/run-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/toolbelt/inertia-table.svg?style=flat-square)](https://packagist.org/packages/toolbelt/inertia-table)
[![Total Downloads](https://img.shields.io/packagist/dt/toolbelt/inertia-table.svg?style=flat-square)](https://packagist.org/packages/toolbelt/inertia-table)

Server-driven data tables for Laravel and Inertia.js, powered by [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder/v7/introduction). Define columns and filters in PHP, pass the table directly as an Inertia prop, and let a frontend renderer keep URL state and server results in sync.

> This package is under active development. The API is not stable until v1.0.

The proposed v0.1 contract is documented in [docs/architecture.md](docs/architecture.md). The current implementation is an exploratory spike until that contract is implemented.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Inertia Laravel 2 or 3
- Spatie Laravel Query Builder 7
- Vue 3.4+, Reka UI 2.10+, and `@lucide/vue` 1.30+
- Tailwind CSS 4.1+ (the Vue renderer vendors shadcn-vue source)

## Installation

```bash
composer require toolbelt/inertia-table
npm install @toolbelt/inertia-table-vue
```

Publish the optional configuration file:

```bash
php artisan vendor:publish --tag="inertia-table-config"
```

## Defining a table

Create a table class in your application:

```php
<?php

namespace App\Tables;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Toolbelt\InertiaTable\Columns\BooleanColumn;
use Toolbelt\InertiaTable\Columns\NumberColumn;
use Toolbelt\InertiaTable\Columns\TextColumn;
use Toolbelt\InertiaTable\Filters\SetFilter;
use Toolbelt\InertiaTable\Table;

final class TopicsTable extends Table
{
    protected ?string $defaultSort = 'name';

    public function query(): Builder
    {
        return Topic::query()->withCount('quotes');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->searchable()->sortable(),
            NumberColumn::make('quotes_count', 'Quotes')->sortable(),
            BooleanColumn::make('is_featured', 'Featured'),
        ];
    }

    public function filters(): array
    {
        return [
            SetFilter::make('status')
                ->options([
                    'empty' => 'Without quotes',
                    'featured' => 'Featured',
                ])
                ->applyUsing(function (Builder $query, string $value) {
                    match ($value) {
                        'empty' => $query->doesntHave('quotes'),
                        'featured' => $query->where('is_featured', true),
                    };
                }),
        ];
    }
}
```

Global search is inferred from columns marked `searchable()`. It may also be
declared explicitly on the table; an empty array disables it even when a column
is searchable:

```php
protected array|string|null $search = ['name', 'email'];
// protected array|string|null $search = [];
```

Filters ship with type-specific clauses through `TextFilter`, `SetFilter`,
`NumericFilter`, `BooleanFilter`, and `DateFilter`. `SelectFilter` remains as a
deprecated alias of `SetFilter` for backwards compatibility.

Columns support named arguments and fluent methods for server-declared
presentation:

```php
TextColumn::make(
    'description',
    sortable: true,
    wrap: true,
    truncate: 2,
    tooltip: 'Public description',
    headerClass: 'font-semibold',
    cellClass: 'max-w-md',
)->mapAs(fn (string $value) => trim($value));

BadgeColumn::make('status')
    ->mapAs(['active' => 'Active', 'blocked' => 'Blocked'])
    ->variant(['active' => 'success', 'blocked' => 'danger'])
    ->icon(['active' => 'CheckCircle', 'blocked' => 'XCircle']);
```

Available built-in types are `TextColumn`, `NumberColumn`, `BadgeColumn`,
`BooleanColumn`, `DateColumn`, `DateTimeColumn`, `ImageColumn`, and
`ActionColumn`.

Images are configured in PHP and rendered by the default Vue table:

```php
TextColumn::make('name')->image('avatar_url', fn (Image $image) => $image
    ->rounded()
    ->large()
    ->alt('User avatar'));

ImageColumn::make('avatar_url')->image(fn (User $user, Image $image) => $image
    ->url($user->friends->pluck('avatar_url')->all())
    ->limit(3)
    ->rounded());
```

The advanced Vue escape hatches are `#image(attribute)` and
`#image-fallback(attribute)`.

Pass it directly to an Inertia page:

```php
return inertia('Admin/Topics/Index', [
    'topics' => TopicsTable::make()
        ->reloadProps(['featuredCount', 'trashedCount']),
]);
```

Render the resource in Vue. The renderer vendors the shadcn-vue component source it needs, so it follows the host application's shadcn theme without importing an application-specific `@/components/ui` alias. With Tailwind CSS v4, add the package source to your application stylesheet:

```css
/* resources/css/app.css */
@source '../../node_modules/@toolbelt/inertia-table-vue/resources/js/**/*.vue';
```

```vue
<script setup lang="ts">
import {
    DataTable,
    type TableResource,
} from '@toolbelt/inertia-table-vue';

type Topic = {
    id: number;
    name: string;
    quotes_count: number;
    is_featured: boolean;
};

defineProps<{
    topics: TableResource<Topic>;
}>();
</script>

<template>
    <DataTable :resource="topics" selectable>
        <template #cell(name)="{ item }">
            <strong>{{ item.name }}</strong>
        </template>
    </DataTable>
</template>
```

## Action icons

Declare an icon name, label visibility, and tooltip with the action in PHP:

```php
Action::make('edit', 'Edit')
    ->row()
    ->icon('Pencil')
    ->hideLabel()
    ->tooltip('Edit topic')
    ->endpoint('get', fn (Topic $topic) => route('topics.edit', $topic));
```

The package does not couple PHP to an icon library. Register a resolver once in
your application entry point:

```ts
import { Pencil, Trash2 } from '@lucide/vue';
import { setIconResolver } from '@toolbelt/inertia-table-vue';

const icons = { Pencil, Trash2 };

setIconResolver((name) => icons[name]);
```

You may override the global resolver for one table with the `iconResolver` prop:

```vue
<DataTable :resource="topics" :icon-resolver="resolveTopicIcon" />
```

The resolver receives the serialized action as its second argument. Icon-only
actions retain their label as an accessible `aria-label`.

## Row action state and frontend handlers

Row actions can vary for each model. `hidden()` removes an action from that
row; `disabled()` keeps it visible but prevents execution. A disabled tooltip
takes precedence over the usual tooltip.

```php
Action::make('archive', fn (Topic $topic) => "Archive {$topic->name}")
    ->icon('Archive')
    ->disabled(fn (Topic $topic) => $topic->archived_at !== null)
    ->disabledTooltip('This topic is already archived')
    ->confirm();
```

`confirm()` may be called without arguments. Its defaults are “Confirm action”,
“Are you sure you want to perform this action?”, “Yes”, and “Cancel”.

Omit `endpoint()` for a frontend-owned action. The table emits `custom-action`
with the action, selected keys, and an `onFinish` callback. Call it when the
custom work completes to clear its pending state:

```vue
<DataTable :resource="topics" @custom-action="handleCustomAction" />
```

```ts
function handleCustomAction(action, keys, onFinish) {
    // Open a modal, call another API, or perform any client-side work.
    onFinish();
}
```

Endpoint-backed actions also emit `action-success` and `action-error`. The
endpoint supports `get`, `post`, `put`, `patch`, and `delete` methods.

For a custom renderer, import the headless composable:

```ts
import { useDataTable } from '@toolbelt/inertia-table-vue';

const table = useDataTable(() => props.topics);
```

It exposes URL navigation, debounced search, sorting, filtering, pagination, per-page selection, loading state, and current-page row selection.

## Query-string state

Each table owns a namespaced section of the query string. This allows multiple tables to coexist on one page without state collisions.

```text
?table[topics][search]=life
&table[topics][sort]=-created_at
&table[topics][filters][status]=featured
&table[topics][page]=2
&table[topics][perPage]=25
```

Toolbelt translates this namespaced state into Spatie Query Builder's `sort` and `filter` request contract internally. Column definitions compile to `AllowedSort` instances, while filter definitions compile to `AllowedFilter` instances. Unknown sorts, filters, per-page values, and malformed input are discarded or replaced with safe defaults before the query executes.

## Resource contract

The table serializes to a versioned Inertia resource:

```json
{
    "schemaVersion": 1,
    "name": "topics",
    "search": ["name"],
    "columns": [],
    "filters": [],
    "state": {
        "search": "",
        "sort": "name",
        "filters": {
            "status": {"enabled": false, "clause": "in", "value": null}
        },
        "page": 1,
        "perPage": 25
    },
    "results": {
        "data": [],
        "currentPage": 1,
        "from": null,
        "lastPage": 1,
        "links": [],
        "perPage": 25,
        "to": null,
        "total": 0
    },
    "reloadProps": [],
    "debounceTime": 300,
    "perPageOptions": [10, 25, 50, 100]
}
```

## Development

```bash
composer install
composer test
composer analyse
composer format
```

## License

The MIT License. See [LICENSE.md](LICENSE.md).
