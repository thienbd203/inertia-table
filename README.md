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
use Toolbelt\InertiaTable\Filters\SelectFilter;
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
            SelectFilter::make('status')
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

Pass it directly to an Inertia page:

```php
return inertia('Admin/Topics/Index', [
    'topics' => TopicsTable::make()
        ->reloadProps(['featuredCount', 'trashedCount']),
]);
```

Render the resource in Vue:

The default renderer ships with its own minimal shadcn-vue component set built on Reka UI. It uses shadcn CSS variables such as `--background`, `--foreground`, `--primary`, `--border`, and `--radius`, so it follows the host application's theme without importing components through an application-specific alias.

```vue
<script setup lang="ts">
import {
    DataTable,
    type TableResource,
} from '@toolbelt/inertia-table-vue';
import '@toolbelt/inertia-table-vue/style.css';

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
    "columns": [],
    "filters": [],
    "state": {
        "search": "",
        "sort": "name",
        "filters": {},
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
