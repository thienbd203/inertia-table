# Getting started

Create a searchable, sortable Laravel table and render it in Vue. The Laravel
package defines what the browser may request; the Vue package renders that
definition and keeps its state in the URL.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- Inertia Laravel 2 or 3
- Vue 3.4 or newer
- Tailwind CSS 4.1 or newer

See the full [compatibility matrix](/reference/compatibility) for frontend peer
dependencies.

## Install both packages

```bash
composer require musing/inertia-table
npm install @musing/inertia-table-vue
```

The Vue renderer ships source components. Add the package path to the host
application's Tailwind stylesheet:

```css
/* resources/css/app.css */
@source '../../node_modules/@musing/inertia-table-vue/resources/js/**/*.vue';
```

Import the renderer stylesheet once in your application entry point:

```ts
import "@musing/inertia-table-vue/style.css";
```

The renderer uses the application's existing Tailwind theme variables. It does
not require an application `@/components/ui` alias.

## Generate a table class

```bash
php artisan make:inertia-table TopicsTable --model=Topic
```

Define the Eloquent query and the columns available to the browser:

```php
<?php

namespace App\Tables;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\Columns\DateTimeColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Table;

final class TopicsTable extends Table
{
    protected ?string $defaultSort = 'name';

    public function query(): Builder
    {
        return Topic::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')
                ->searchable()
                ->sortable(),
            DateTimeColumn::make('created_at', 'Created')
                ->sortable(),
        ];
    }
}
```

Only columns declared by the table may become search or sort expressions. Raw
column names from the query string never become SQL identifiers.

## Return it from an Inertia controller

```php
use App\Tables\TopicsTable;

return inertia('Topics/Index', [
    'topics' => TopicsTable::make(),
]);
```

`Table` implements Laravel's `Arrayable` contract. Inertia resolves it when the
response is serialized, so normal partial reload behavior remains available.

## Render the table

```vue
<script setup lang="ts">
import {
    DataTable,
    type TableResource,
} from "@musing/inertia-table-vue";

type Topic = {
    id: number;
    name: string;
    created_at: string;
};

defineProps<{ topics: TableResource<Topic> }>();
</script>

<template>
    <DataTable :resource="topics" />
</template>
```

You now have server-side search, sorting, pagination, URL state, column
visibility, and the standard empty-results UI.

## Add a filter

```php
use Musing\InertiaTable\Filters\SetFilter;

public function filters(): array
{
    return [
        SetFilter::make('status', 'Status')->options([
            'published' => 'Published',
            'draft' => 'Draft',
        ]),
    ];
}
```

Continue with [table definitions](/guide/table-definitions), [columns](/guide/columns),
or [search and filters](/guide/search-and-filters).

## Optional published configuration

The defaults work without publishing a config file. Publish it only when the
application needs global defaults:

```bash
php artisan vendor:publish --tag=inertia-table-config
```

See [configuration](/guide/configuration) for common changes and the
[configuration reference](/reference/configuration) for every key.

## Troubleshooting

### The table is unstyled

Confirm that the Tailwind `@source` directive points to the package's Vue source
and that `@musing/inertia-table-vue/style.css` is imported once.

### A sort or filter is ignored

The requested capability must be declared by the table. Mark a column
`sortable()` or `searchable()`, and return filters from `filters()`.

### A relationship sort fails

To-one automatic relationship sorting requires the optional Power Joins
adapter. Read [relationship queries](/guide/relationships) before adding the
dependency.
