# Musing Inertia Table

[![PHP tests](https://github.com/thienbd203/inertia-table/actions/workflows/run-tests.yml/badge.svg)](https://github.com/thienbd203/inertia-table/actions/workflows/run-tests.yml)
[![JavaScript tests](https://github.com/thienbd203/inertia-table/actions/workflows/run-js-tests.yml/badge.svg)](https://github.com/thienbd203/inertia-table/actions/workflows/run-js-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/musing/inertia-table?style=flat-square)](https://packagist.org/packages/musing/inertia-table)
[![Total Downloads](https://img.shields.io/packagist/dt/musing/inertia-table?style=flat-square)](https://packagist.org/packages/musing/inertia-table)

**Server-driven data tables for Laravel and Inertia.js.** Define the table once in PHP—columns, sorting, search, filters and actions—and render it in Vue with one component.

Toolbelt keeps the server authoritative. The browser can only request capabilities declared by the table, URL state is namespaced per table, and query execution is powered by [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder/v7/introduction).

> [!WARNING]
> The package is actively developed before `v1.0`. Please expect API changes between minor releases.

## Highlights

- PHP-first definitions for columns, filters, row actions and bulk actions.
- Allowlisted search, sort and filter queries—never raw client input in SQL.
- A ready-to-use Vue `<DataTable>` built from shadcn-vue-style source and Reka UI primitives.
- Text, numeric, set, boolean and date filters, including single-date and date-range calendars.
- Per-table query-string state, Inertia partial reloads, pagination, column visibility and all-results selection across pages.
- Scoped saved views with defaults, sharing, optimistic locking and live dirty-state feedback.
- Presentation helpers for badges, dates, images, links, tooltips, alignment and Tailwind classes.
- Slots and headless composables when the default renderer needs an escape hatch.
- Built-in English and Vietnamese interface messages with per-app and per-table overrides.

## Requirements

| Layer          | Requirement                                           |
| -------------- | ----------------------------------------------------- |
| PHP            | 8.3+                                                  |
| Laravel        | 12 or 13                                              |
| Inertia        | Laravel 2 or 3; Vue 3.4+                              |
| Query engine   | Spatie Laravel Query Builder 7                        |
| Frontend peers | Tailwind CSS 4.1+, Reka UI 2.10+, `@lucide/vue` 1.30+ |

## Installation

Install the Laravel core and Vue renderer:

```bash
composer require musing/inertia-table
npm install @musing/inertia-table-vue
```

## Releases

The Laravel package is distributed through Packagist and the Vue renderer through npm. A pushed Git tag in the form `vX.Y.Z` is the release trigger. The tag must match the `version` in `package.json`.

Before the first release, submit `https://github.com/thienbd203/inertia-table` to Packagist and add an `NPM_TOKEN` repository secret with permission to publish `@musing/inertia-table-vue`. The release workflow runs PHP and JavaScript checks, then publishes the Vue package with npm provenance.

```bash
npm version 0.1.1 --no-git-tag-version
git add package.json package-lock.json
git commit -m "chore: release v0.1.1"
git tag v0.1.1
git push origin main --follow-tags
```

Publish configuration only when you want to change pagination or debounce defaults:

```bash
php artisan vendor:publish --tag=inertia-table-config
```

```php
// config/inertia-table.php
return [
    'per_page' => 25,
    'per_page_options' => [10, 25, 50, 100],
    'debounce' => 300,
    'action_path' => '_inertia-table/actions',
    'view_path' => '_inertia-table/views',
    'views' => ['table' => 'table_views'],
];
```

Saved views are opt-in. Publish and run their migration before enabling them on
a table:

```bash
php artisan vendor:publish --tag=inertia-table-migrations
php artisan migrate
```

### Tailwind CSS v4

The renderer contains Vue source that Tailwind must scan. Add this to the host application's stylesheet:

```css
/* resources/css/app.css */
@source '../../node_modules/@musing/inertia-table-vue/resources/js/**/*.vue';
```

The package uses your existing shadcn/Tailwind CSS variables. It does not require an application `@/components/ui` alias.

## Quick start

Create a table definition. This is the source of truth for what users can do.

```php
<?php

namespace App\Tables;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\BadgeColumn;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Variant;

final class TopicsTable extends Table
{
    protected ?string $defaultSort = 'name';

    // Optional: override the global per_page / per_page_options config for this table only.
    protected ?int $perPage = 50;

    protected ?array $perPageOptions = [25, 50, 100];

    public function query(): Builder
    {
        return Topic::query()->withCount('quotes');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->searchable()->sortable(),
            NumberColumn::make('quotes_count', 'Quotes')->sortable(),
            BadgeColumn::make('is_featured', 'Featured')
                ->mapAs(fn (bool $value) => $value ? 'Featured' : 'Normal')
                ->variant(fn (bool $value) => $value ? Variant::Success : Variant::Default),
        ];
    }

    public function filters(): array
    {
        return [
            SetFilter::make('status', 'Status')->options([
                'published' => 'Published',
                'draft' => 'Draft',
            ]),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('edit', 'Edit')
                ->row()
                ->icon('Pencil')
                ->hideLabel()
                ->tooltip('Edit topic')
                ->endpoint('get', fn (Topic $topic) => route('topics.edit', $topic)),
        ];
    }
}
```

Pass it directly to the Inertia page:

```php
return inertia('Admin/Topics/Index', [
    'topics' => TopicsTable::make()
        ->reloadProps(['featuredCount']),
]);
```

Then render it. For most screens this is all the frontend code required.

```vue
<script setup lang="ts">
import { DataTable, type TableResource } from "@musing/inertia-table-vue";

type Topic = {
    id: number;
    name: string;
    quotes_count: number;
    is_featured: boolean;
};

defineProps<{ topics: TableResource<Topic> }>();
</script>

<template>
    <DataTable :resource="topics" />
</template>
```

## Internationalization

The Vue renderer defaults to English and ships with Vietnamese messages. Configure it once on the Vue app:

```ts
import { createInertiaTable, vi } from "@musing/inertia-table-vue";

app.use(
    createInertiaTable({
        locale: "vi-VN",
        messages: vi,
    }),
);
```

The configuration uses Vue provide/inject, so each SSR application instance keeps its own locale. A table can override the app defaults without changing other tables:

```vue
<script setup lang="ts">
import { DataTable, vi } from "@musing/inertia-table-vue";
</script>

<template>
    <DataTable
        :resource="topics"
        locale="vi-VN"
        :messages="{
            ...vi,
            noResults: 'Chưa có chủ đề nào.',
        }"
    />
</template>
```

`locale` controls calendars and locale-sensitive UI. `messages` controls package-owned interface text. Application-owned labels such as column names, filter options and action labels should still be translated by the host application.

Laravel-owned defaults follow `app()->getLocale()` automatically. Publish the language files when an application needs to customize them:

```bash
php artisan vendor:publish --tag=inertia-table-translations
```

## Columns

Built-in types: `TextColumn`, `NumberColumn`, `NumericColumn`, `BadgeColumn`, `BooleanColumn`, `DateColumn`, `DateTimeColumn`, `ImageColumn` and `ActionColumn`.

All content columns support common presentation methods such as `sortable()`, `searchable()`, `toggleable()`, `visible()`, `headerClass()`, `cellClass()`, `tooltip()`, alignment, wrapping and truncation.

```php
TextColumn::make('status')
    ->sortable()
    ->mapAs(['pending' => 'Pending', 'approved' => 'Approved'])
    ->sortUsingMap();

TextColumn::make('description')
    ->wrap()
    ->truncate(2)
    ->cellClass('max-w-md');

DateTimeColumn::make('published_at', 'Published')
    ->format('d/m/Y H:i')
    ->centerAligned();

ActionColumn::new()->asDropdown();
```

`ActionColumn::asDropdown()` groups each row's actions behind one accessible
menu trigger. Dynamic `action(<key>)` slots work in both the inline and dropdown
renderers.

Use a custom sort for expressions, relationships or application-specific ordering:

```php
TextColumn::make('score')->sortable()->sortUsing(
    fn (Builder $query, SortDirection $direction) =>
        $query->orderBy('score', $direction->value),
);
```

### Badges and images

```php
BadgeColumn::make('status')
    ->mapAs(['active' => 'Active', 'blocked' => 'Blocked'])
    ->variant(['active' => Variant::Success, 'blocked' => Variant::Danger])
    ->icon(['active' => 'CheckCircle', 'blocked' => 'XCircle']);

TextColumn::make('name')->image('avatar_url', fn (Image $image) => $image
    ->rounded()
    ->large()
    ->alt('User avatar'));
```

### Navigation

Cell and row URLs can be a string or a `Url` object. The object carries Inertia navigation options to the renderer.

```php
TextColumn::make('name')->url(
    fn (Topic $topic, Url $url) => $url
        ->route('topics.edit', $topic)
        ->openInNewTab(),
);
```

Tables deliberately do not make rows clickable by default. Handle the optional `row-click` event when a screen needs it:

```vue
<DataTable
    :resource="topics"
    @row-click="(item, column) => inspect(item, column)"
/>
```

## Search and filters

Mark columns as `searchable()` for global search. Override the resolved list on the table when necessary:

```php
protected array|string|null $search = ['name', 'email'];

// Explicitly disable global search, even if a column is searchable.
protected array|string|null $search = [];
```

Available filter classes and their default behaviour:

| Filter          | Typical clauses                           |
| --------------- | ----------------------------------------- |
| `TextFilter`    | contains, starts with, equals, not equals |
| `NumericFilter` | comparison, equals and range clauses      |
| `SetFilter`     | in, not in, equals, not equals            |
| `BooleanFilter` | true / false                              |
| `DateFilter`    | before, after, equals, and date ranges    |

```php
TextFilter::make('name', 'Name')->clauses([
    'contains', 'starts_with', 'equals', 'not_equals',
]);

SetFilter::make('category_id', 'Category')->options(
    Category::query()->orderBy('name')->pluck('name', 'id')->all(),
);

DateFilter::make('created_at', 'Created at');
```

`SetFilter` presents a multi-select UI for `in` and `not_in`. `DateFilter` presents an inline calendar for a single date and a two-month range calendar for `between` and `not_between`.

For application-specific query logic, use `applyUsing()` and retain the declared option allowlist:

```php
SetFilter::make('status')->options([
    'empty' => 'Without quotes',
    'featured' => 'Featured',
])->applyUsing(function (Builder $query, string|array $value, string $clause) {
    $values = (array) $value;

    if ($clause === 'equals' && $values[0] === 'empty') {
        $query->doesntHave('quotes');
    }
});
```

`SelectFilter` is available as a deprecated alias for `SetFilter`.

## Actions

Actions are server-declared and can be row-level, bulk or both. Authorization, visibility, disabled state and action labels may vary per model.

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Musing\InertiaTable\Selection;

Action::make('delete', 'Delete')
    ->row()
    ->destructive()
    ->icon('Trash2')
    ->hideLabel()
    ->tooltip('Delete topic')
    ->authorized(fn (Topic $topic) => auth()->user()->can('delete', $topic))
    ->handle(fn (Topic $topic) => $topic->delete())
    ->confirm('Delete topic?', 'This cannot be undone.', 'Delete', 'Cancel');

Action::make('archive', 'Archive')
    ->bulk()
    ->authorize(fn (Request $request) => $request->user()->can('update', Topic::class))
    ->before(fn (Selection $selection) => Log::info('Archiving topics', [
        'count' => $selection->count(),
    ]))
    ->handleSelection(fn (Selection $selection) => $selection
        ->query()
        ->update(['archived_at' => now()]))
    ->after(fn () => session()->flash('success', 'Topics archived'));
```

`handle()` invokes the closure once per selected model in chunks, which is useful when model events must run. Use `chunkSize()` to override the default chunk size of 1,000. During bulk execution, unselectable models and models whose row action is unauthorized, disabled or hidden are skipped. `handleSelection()` invokes the closure once with a typed `Selection`, which is useful for a set-based update over a large filtered result; because it operates directly on the query, its callback owns any additional per-model eligibility constraints that cannot be represented by `selectableQuery()`.

`before()` and `after()` wrap the handler once per action request and receive the `Selection`. An `after()` callback may return a response or URL, and `after('/topics/archived')` provides a direct redirect. Use `authorize()` for request-level authorization and `authorized()` for model-level row authorization. Handler actions automatically receive a signed internal POST endpoint under the configured `action_path`; action scope and availability are checked again when that endpoint runs.

Managed endpoints use Laravel's normal response contract consistently: returned `Response`/`Responsable` values pass through, successful handlers without one redirect back, unavailable actions return `403`, disabled row actions return validation errors, and unexpected exceptions remain visible to Laravel's exception handler.

The `Selection` query always starts from `Table::query()` and applies `selectableQuery()`. For an all-results selection, it rebuilds the query through the table's declared search/filter allowlist and applies the unchecked keys from `except`. Useful APIs are `query()`, `count()`, `get()`, `firstOrFail()` and memory-safe `each()`.

Declare bulk eligibility at both query and row level. The query scope gives the frontend an exact `selectableTotal` without loading every model; the row check disables individual checkboxes. Keep both rules equivalent whenever possible:

```php
public function selectableQuery(Builder $query): Builder
{
    return $query->whereNull('locked_at');
}

public function isSelectable(Model $model): bool
{
    return $model->locked_at === null;
}
```

An unselectable row may still expose row actions. Selectability only defines the bulk-selection boundary.

Use `endpoint()` when an existing application route should own the action instead:

```php
Action::make('edit')
    ->row()
    ->endpoint('get', fn (Topic $topic) => route('topics.edit', $topic));
```

Omit both `handle()` and `endpoint()` for a frontend-owned action. The component emits `custom-action` with `(action, keys, onFinish, selection)`; call `onFinish()` after the custom work completes. Existing handlers can keep using the first three arguments.

```vue
<DataTable :resource="topics" @custom-action="handleCustomAction" />
```

The header checkbox immediately selects every selectable result matching the current search and filters, across all pages. Its label and selected count use the exact server-provided `selectableTotal`; there is no intermediate "current page" selection step. The checkbox is empty, indeterminate, or checked, and clicking the indeterminate state always resolves to select-all. Individual rows can then be unchecked and are tracked in `selection.except`. Shift-clicking a row checkbox applies the target checkbox state to the contiguous range from the previously clicked row on the current page while skipping disabled checkboxes.

Explicit bulk selections keep the existing `{ ids: [...] }` request payload. Selecting all matching results sends a selection descriptor instead of attempting to load every ID into the browser:

```ts
{
    ids: [],
    selection: {
        all: true,
        keys: [],
        except: [42],
        table: "topics",
        state: {
            search: "laravel",
            filters: { status: { enabled: true, clause: "equals", value: "published" } },
        },
    },
}
```

Managed handlers resolve this descriptor through `Selection` automatically. Application-owned `endpoint()` routes remain responsible for resolving it safely and must not apply raw client attributes directly to SQL.

Confirmation text supports `:count` for row and bulk actions, plus scalar row attributes such as `:name`. This keeps destructive confirmation copy honest without loading all matching IDs into the browser:

```php
Action::make('delete', 'Delete')
    ->bulk()
    ->destructive()
    ->confirm(
        'Delete :count selected topics?',
        'You selected :count matching topics to move to trash.',
        'Delete :count',
);
```

The title and message may instead contain singular, plural, and optional
all-matching variants. An all-matching selection uses the third variant and
falls back to the plural variant when it is omitted:

```php
->confirm(
    [
        'Delete :count topic?',
        'Delete :count topics?',
        'Delete all :count matching topics?',
    ],
    [
        'This topic will be deleted.',
        ':count topics will be deleted.',
        'All :count matching topics will be deleted.',
    ],
    'Delete :count',
);
```

Action icons are intentionally library-agnostic. Register your Lucide resolver once:

```ts
import { Pencil, Trash2 } from "@lucide/vue";
import { setIconResolver } from "@musing/inertia-table-vue";

setIconResolver((name) => ({ Pencil, Trash2 })[name]);
```

## Slots and headless API

The default renderer is intended to cover normal tables. Use slots only for targeted customisation.

```vue
<DataTable :resource="topics">
  <template #cell(name)="{ item }">
    <strong>{{ item.name }}</strong>
  </template>

  <template #emptyState>
    <p class="py-10 text-center text-muted-foreground">No topics found.</p>
  </template>
</DataTable>
```

Useful slots include `topbar`, `beforeSearch`, `afterSearch`, `beforeActions`, `afterActions`, `filters`, `table`, `thead`, `tbody`, `footer`, `loading`, `emptyState`, `confirmation`, `cell(attribute)`, `header(attribute)`, `filter(attribute)`, `image(attribute)` and `image-fallback(attribute)`.

Use `filter(attribute)` when an option source needs application-owned behavior such as remote search, pagination or creating a missing option. The slot receives `filter`, `state`, `value`, `update`, `setDisplayValue`, `close`, `table` and `actions`:

Declare the stored value with a regular server-side filter. For example, an integer foreign key can use a clause-less numeric filter:

```php
NumericFilter::make('source_id', 'Source')->withoutClause();
```

```vue
<DataTable :resource="quotes">
  <template
    #filter(source_id)="{ value, update, setDisplayValue, close }"
  >
    <AsyncSourceSelect
      :model-value="value"
      @select="({ value: nextValue, label }) => {
        update(nextValue);
        setDisplayValue(label);
        close();
      }"
    />
  </template>
</DataTable>
```

The package only owns the selected filter value and URL state in this case. The application owns the endpoint, loading state, debounce, result pagination and option creation.

For a fully custom renderer, use the composables instead:

```ts
import { useActions, useTable } from "@musing/inertia-table-vue";

const table = useTable(() => props.topics);
const actions = useActions(table);
```

Laravel resources include the model's Eloquent primary key as stable row metadata, so selection also works for UUIDs and primary keys not named `id`. When rendering an application-owned resource, override the identity explicitly:

```vue
<DataTable :resource="topics" :row-key="(topic) => topic.uuid" />
```

The equivalent headless option is `useActions(table, { rowKey: (topic) => topic.uuid })`. Selection persists across pagination and is cleared when the active search or filters change.

## URL state and multiple tables

Every table gets an isolated query-string namespace. Several table resources may live on one Inertia page without overwriting one another.

```text
?table[topics][search]=laravel
&table[topics][sort]=-created_at
&table[topics][filters][status][enabled]=1
&table[topics][filters][status][clause]=equals
&table[topics][filters][status][value]=featured
&table[topics][columns][created_at]=0
&table[topics][page]=2
&table[topics][perPage]=25
```

Toolbelt translates this state to Spatie's query contract internally. Invalid columns, sorts, clauses, filter values and page sizes are ignored or replaced by safe defaults before the query executes.

## Saved views

Enable saved views by returning a `Views` definition from the table. The default
scope belongs to the authenticated Laravel user:

```php
use Musing\InertiaTable\Views;

public function views(): ?Views
{
    return Views::make();
}
```

The toolbar then provides the view switcher and create, update, rename, delete,
default and share operations allowed by the server. View state contains sort,
filters, column visibility, pinned-column metadata and page size. Search remains
ephemeral unless `includeSearch()` is enabled:

```php
public function views(): ?Views
{
    return Views::make()
        ->includeSearch()
        ->scopeTableName()
        ->attributes(fn () => ['tenant_id' => tenant()->id]);
}
```

`attributes()` isolates otherwise identical tables by application context, such
as tenant or workspace. `scopeTableName()` additionally isolates multiple named
instances of the same PHP table class. Use `scopeUser(false)` for application-wide
views, `userResolver()` for a non-standard identity source, and `modelClass()` for
a `TableView` subclass. Fine-grained policies are available through
`authorizeCreate()`, `authorizeUpdate()`, `authorizeDelete()`, `authorizeShare()`
and `authorizeDefault()`.

State precedence is explicit URL values over the selected view, then the user's
default view, then table defaults. Stored values are normalized against the
table's current columns, filters and per-page allowlist whenever they are read,
so stale definitions cannot restore undeclared query capabilities. CRUD uses
signed, CSRF-protected routes and a `lock_version`; concurrent stale edits are
rejected instead of silently overwriting a newer view.

For a custom renderer, compose the view controller with the same table instance:

```ts
import { useTable, useViews } from "@musing/inertia-table-vue";

const table = useTable(() => props.topics);
const views = useViews(table);
```

## Development

```bash
composer install
composer test
composer analyse
composer format

npm install
npm run format:check
npm run types:check
npm test
npm run build
```

The design and resource contract are described in [docs/architecture.md](docs/architecture.md).

## License

The MIT License. See [LICENSE.md](LICENSE.md).
