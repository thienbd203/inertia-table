# Musing Inertia Table

[![PHP tests](https://github.com/thienbd203/inertia-table/actions/workflows/run-tests.yml/badge.svg)](https://github.com/thienbd203/inertia-table/actions/workflows/run-tests.yml)
[![JavaScript tests](https://github.com/thienbd203/inertia-table/actions/workflows/run-js-tests.yml/badge.svg)](https://github.com/thienbd203/inertia-table/actions/workflows/run-js-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/musing/inertia-table?style=flat-square)](https://packagist.org/packages/musing/inertia-table)
[![Total Downloads](https://img.shields.io/packagist/dt/musing/inertia-table?style=flat-square)](https://packagist.org/packages/musing/inertia-table)

**Server-driven data tables for Laravel and Inertia.js.** Define the table once in PHP—columns, sorting, search, filters and actions—and render it in Vue with one component.

Musing Inertia Table keeps the server authoritative. The browser can only request capabilities declared by the table, URL state is namespaced per table, and query execution is powered by [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder/v7/introduction).

> [!WARNING]
> The package is actively developed before `v1.0`. Please expect API changes between minor releases.

## Highlights

- PHP-first definitions for columns, filters, row actions and bulk actions.
- Allowlisted search, sort and filter queries—never raw client input in SQL.
- A ready-to-use Vue `<DataTable>` built from shadcn-vue-style source and Reka UI primitives.
- Text, numeric, set, boolean and date filters, including single-date and date-range calendars.
- Per-table query-string state, Inertia partial reloads, pagination, column visibility, sticky headers/columns and all-results selection across pages.
- Scoped saved views with defaults, sharing, optimistic locking and live dirty-state feedback.
- Signed synchronous or queued exports for all, filtered or selected rows, plus optional XLSX/PDF adapters.
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
git push origin master --follow-tags
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
    'pagination_type' => 'full',
    'debounce' => 300,
    'sticky' => [
        'backdrop_filter' => true,
    ],
    'action_path' => '_inertia-table/actions',
    'export_path' => '_inertia-table/exports',
    'relationship_sorter' => \Musing\InertiaTable\Sorters\PowerJoinsRelationshipSorter::class,
    'queue' => [
        'connection' => null,
        'queue' => null,
        'delay' => 0,
        'disk' => 'local',
        'path' => 'table-exports',
        'expires_after' => 604800,
    ],
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

Generate a dedicated table class when the table needs row actions, bulk actions,
Saved Views or exports:

```bash
php artisan make:inertia-table TopicsTable
php artisan make:inertia-table Admin/TopicsTable --model=Content/Topic
```

The command writes to `app/Tables`, infers a singular model when `--model` is
omitted, and refuses to replace an existing class unless `--force` is passed.

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
use Musing\InertiaTable\PaginationType;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Variant;

final class TopicsTable extends Table
{
    protected ?string $defaultSort = 'name';

    // Optional: override the global per_page / per_page_options config for this table only.
    protected ?int $perPage = 50;

    protected ?array $perPageOptions = [25, 50, 100];

    protected ?PaginationType $paginationType = PaginationType::Full;

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

### Anonymous tables

Simple read-only tables can be defined inline without a dedicated class. The
resource may be an Eloquent model class or an existing Eloquent builder; the
builder is cloned before each resolution so the table cannot mutate the
controller's query.

```php
use App\Models\Topic;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Table;

return inertia('Admin/Topics/Index', [
    'topics' => Table::build(
        resource: Topic::query()->where('archived', false),
        columns: [
            TextColumn::make('name')->searchable()->sortable(),
        ],
        filters: [
            BooleanFilter::make('is_featured', 'Featured'),
        ],
        name: 'topics',
        defaultSort: 'name',
        perPageOptions: [25, 50, 100],
        defaultPerPage: 25,
        transformModelUsing: fn (Topic $topic) => [
            ...$topic->toArray(),
            'display_name' => str($topic->name)->headline()->toString(),
        ],
    ),
]);
```

`Table::build()` also accepts `search`, `pagination`, `paginationType`,
`debounceTime`, `withQueryBuilder`, `emptyState`, `stickyHeader` and
`stickyBackdropFilter`. Set
`pagination: false` to return the complete normalized result and remove page
controls. Anonymous
tables intentionally do not declare actions, exports or Saved Views; use a
generated class when server-managed behavior or persisted state is required.

### Pagination modes

Choose a pagination strategy globally with `pagination_type`, per table with a
property, or at runtime with `paginationType()`:

```php
use Musing\InertiaTable\PaginationType;

protected ?PaginationType $paginationType = PaginationType::Simple;

TopicsTable::make()->paginationType(PaginationType::Cursor);
```

| Mode     | Query behavior                         | Renderer controls                                       |
| -------- | -------------------------------------- | ------------------------------------------------------- |
| `Full`   | Runs the normal exact `COUNT(*)`       | First, previous, five-page number window, next and last |
| `Simple` | Fetches one extra row; no result count | Previous and next, with page number                     |
| `Cursor` | Keyset pagination; no page/count query | Previous and next with opaque cursor                    |

Cursor pagination requires a declared default or requested sort on a plain,
non-null base-table column. The package appends the model's qualified primary
key as a deterministic tie-breaker. Relationship sorts and raw/expression sorts
are rejected because they cannot safely reconstruct cursor boundaries.

Exact all-matching selection is intentionally preserved in every mode. A table
with bulk actions or a selected-scope export still runs a selectable-count query
so the header checkbox and confirmation count stay honest; tables without those
features get the count-query savings of simple or cursor pagination.

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

Every Vietnamese frontend key is type-checked against the English catalog.
Unknown interpolation placeholders are left visible rather than silently
removed. See [docs/customization.md](docs/customization.md) for the ownership
rules, icon overrides, slots, stable CSS hooks and headless customization API.

## Columns

Built-in types: `TextColumn`, `NumberColumn`, `NumericColumn`, `BadgeColumn`, `BooleanColumn`, `DateColumn`, `DateTimeColumn`, `ImageColumn` and `ActionColumn`.

All content columns support common presentation methods such as `sortable()`, `searchable()`, `toggleable()`, `visible()`, `headerClass()`, `cellClass()`, `tooltip()`, alignment, wrapping and truncation.

```php
TextColumn::make('status')
    ->sortable()
    ->mapAs(['pending' => 'Pending', 'approved' => 'Approved'])
    ->sortUsingMap();

TextColumn::make('priority')
    ->sortable()
    ->sortUsingPriority(['urgent', 'normal', 'low']);

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

### Sticky header and columns

Enable a sticky header on one table with a property or the fluent API. The
default renderer gives sticky-header tables a `70vh` scroll viewport so the
header has a vertical scroll container; override
`--tb-sticky-header-max-height` on the wrapper when a screen needs another
height.

```php
final class TopicsTable extends Table
{
    protected ?bool $stickyHeader = true;
}

TopicsTable::make()->stickyHeader();
```

Sticky cells use a backdrop blur by default. Disable it globally when large
tables or many pinned columns make repainting expensive:

```php
// config/inertia-table.php
'sticky' => [
    'backdrop_filter' => false,
],
```

Override the global value for one table with a property or the fluent API:

```php
final class TopicsTable extends Table
{
    protected ?bool $stickyBackdropFilter = false;
}

TopicsTable::make()->stickyBackdropFilter(false);
```

When enabled, customize the CSS filter with
`--tb-sticky-backdrop-filter` (the default is `blur(4px)`). Resources produced
by older package versions do not include the option and remain enabled for
backward compatibility.

`stickable()` lets the user pin or unpin a column from its header menu.
`sticky()` makes the column permanently pinned and works with every column
type, including `ActionColumn`:

```php
public function columns(): array
{
    return [
        NumberColumn::make('id')->sticky(),
        TextColumn::make('name')->stickable(),
        TextColumn::make('email')->stickable(),
        ActionColumn::new()->sticky(),
    ];
}
```

The pin side is inferred from the column's visible position. Adjacent pinned
columns stack measured widths, hidden columns retain their pin preference, and
offsets are recalculated after visibility or responsive width changes. Logical
CSS insets mirror the leading/trailing groups in RTL layouts. Pin state is
namespaced in the table URL and included in Saved Views; it never changes the
search/filter identity used by bulk selection.

Use a custom sort for expressions or application-specific ordering:

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

### Empty states and row data attributes

Return an `EmptyState` when a genuinely empty base table should offer more than
the generic no-results message. It supports a title, message, optional icon,
metadata, normalized `data-*` attributes and URL actions:

```php
use Musing\InertiaTable\EmptyState;
use Musing\InertiaTable\Url;
use Musing\InertiaTable\Variant;

public function emptyState(): ?EmptyState
{
    return EmptyState::make('No topics yet', 'Create the first topic.')
        ->dataAttributes(['kind' => 'topics'])
        ->action(
            label: 'Create topic',
            url: fn (Url $url) => $url->route('topics.create'),
            variant: Variant::Info,
            icon: 'Plus',
        );
}
```

The server only serializes this definition when the unfiltered base query is
empty. A search or filter that happens to match no rows keeps the ordinary
`No results found` UI. The `emptyState` slot remains available to replace the
default renderer.

Add safe per-row DOM hooks without leaking arbitrary HTML attributes by
returning keys without the `data-` prefix. The callback receives both the model
and the transformed row data:

```php
public function dataAttributesForModel(Model $model, array $data): array
{
    return [
        'record-id' => $model->getKey(),
        'status' => $data['status_label'],
    ];
}
```

Only scalar or null values are accepted. Package-owned `data-selected` and
`data-row-clickable` state cannot be overwritten.

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

### Relationship queries

Declared columns and filters accept nested Eloquent paths. Dot notation is never
read directly from client input: only paths present in `columns()` or `filters()`
can become query constraints.

```php
public function columns(): array
{
    return [
        TextColumn::make('author.name', 'Author')->searchable()->sortable(),
        TextColumn::make('author.company.name', 'Company')->searchable(),
        NumberColumn::make('comments.score', 'Comment score')->sortable(),
    ];
}

public function filters(): array
{
    return [
        TextFilter::make('author.company.name', 'Company'),
        NumericFilter::make('comments.score', 'Comment score'),
    ];
}
```

Search and filters use nested `whereHas` constraints, so has-many matches do not
duplicate base rows. Direct columns are qualified with the model table to avoid
ambiguous-column errors. Nullable relationship filters include a missing relation
when using `is_not_set`.

To-one relationship sorting uses the optional Eloquent Power Joins adapter and a
left join, preserving rows whose relationship is null:

```bash
composer require kirschbaum-development/eloquent-power-joins
```

To-many sorting stays duplicate-safe by ordering on a correlated `MIN` for
ascending order or `MAX` for descending order. Use `sortUsing()` when a domain
needs different aggregation or ordering semantics. The global adapter is
configured at `inertia-table.relationship_sorter`.

Tables can customize the same Spatie query builder used by results, explicit and
all-matching selections, and every export scope:

```php
use Spatie\QueryBuilder\QueryBuilder;

protected function withQueryBuilder(QueryBuilder $query): QueryBuilder
{
    $query->where('topics.tenant_id', tenant()->id);

    return $query;
}
```

When the base query or hook adds joins, the package selects the base model and
deduplicates by its qualified primary key so pagination totals and exported rows
remain stable. Application-owned joined projections and custom sort callbacks
remain responsible for their own SQL portability.

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

## Exports

Declare one or more authorized export options on the table. Native CSV has no
additional dependency and defaults to the full base query:

```php
use Illuminate\Http\Request;
use Musing\InertiaTable\Exports\Export;

public function exports(): array
{
    return [
        Export::make('all', 'All topics'),
        Export::make('filtered', 'Filtered topics')->filtered(),
        Export::make('selected', 'Selected topics')->selected(),
        Export::make('excel', 'Excel', type: 'xlsx')
            ->filtered()
            ->authorize(fn (Request $request) => $request->user()->can('export')),
    ];
}
```

`allRows()` uses `Table::query()` without applying the browser state.
`filtered()` runs the current search, filters and sort through the same server
normalization as the visible table. `selected()` reuses the typed `Selection`,
including all-matching selections and exclusions. A selected export enables row
checkboxes even when the table has no bulk actions, and starting a download never
clears the current selection.

Columns are exportable by default except `ActionColumn`. Customize the resolved
value or exclude a column without changing its onscreen renderer:

```php
TextColumn::make('reference')
    ->exportAs(fn (string $value, Topic $topic) => "#{$value}");

TextColumn::make('internal_notes')->dontExport();

NumberColumn::make('amount')
    ->exportFormat('#,##0.00')
    ->exportMeta(['style' => ['font' => ['bold' => true]]]);
```

Declared exportable columns are used by default. Call `visibleColumnsOnly()` on
an export when it should follow the normalized column visibility state. Native
CSV reads Eloquent models in eager-loaded chunks, streams each row immediately,
emits UTF-8 with a BOM by default, and protects spreadsheet formula prefixes.
The default chunk size is `inertia-table.exports.chunk_size` (1,000). Override it
for a specific definition with `chunkSize()`:

```php
use Illuminate\Database\Eloquent\Builder;

Export::make('archive')
    ->filtered()
    ->chunkSize(2_000)
    ->modifyQueryUsing(fn (Builder $query) => $query->select([
        'topics.id',
        'topics.name',
    ]));
```

Query modifiers run after the export scope, filters and sort are resolved. They
may mutate the builder or return another Eloquent builder, which lets expensive
table-only selects, counts or relationships be replaced for an export. Use
`meta(['delimiter' => ';', 'bom' => false])` to customize CSV output. The same
resolved chunk size is passed to the optional Laravel Excel adapter.

XLSX and PDF use the optional Laravel Excel adapter:

```bash
composer require maatwebsite/excel
```

The base package does not require Laravel Excel. Requesting one of those formats
without it returns a clear validation error. Custom formats implement
`Musing\InertiaTable\Contracts\Exporter` and are registered under
`inertia-table.exporters.<type>`.

Call `queue()` when the export should run outside the request. The worker rebuilds
the table and query from a normalized, serializable snapshot; it never receives a
live request, query builder, table instance or definition closure:

```php
use Illuminate\Support\Facades\Storage;
use Musing\InertiaTable\Exports\QueuedExportSnapshot;

Export::make('archive', 'Export archive')
    ->filtered()
    ->queue(
        connection: 'redis',
        queue: 'exports',
        delay: 5,
        disk: 's3',
        expiresAfter: 86_400,
    )
    ->redirectAfterDispatch('/exports')
    ->deliveryUrlUsing(
        fn (QueuedExportSnapshot $snapshot) => Storage::disk($snapshot->disk)
            ->temporaryUrl($snapshot->path, now()->addHour()),
    )
    ->onReady(fn (QueuedExportSnapshot $snapshot, ?string $url) => /* notify */ null)
    ->onFailure(fn (QueuedExportSnapshot $snapshot, \Throwable $exception) => /* report */ null);
```

Queue connection, name, delay, disk, path and expiry fall back to
`inertia-table.queue`. Every dispatch carries an idempotency key, so duplicate
submissions for the same actor and scoped export reuse the existing job. Completed
files are deleted after expiry, and partial files are removed when generation
fails. `chain()` accepts follow-up job objects.

The default context captures the authenticated actor and restores it in the
worker before authorization is checked again. Multi-tenant applications can add
scalar tenant identifiers with `scopeAttributes()` and provide an
`ExportContext` implementation via `context()` to restore and release tenant
state. Definitions removed or materially changed after dispatch fail safely
instead of exporting with different semantics.

The Vue renderer submits signed POST requests and reads Laravel's CSRF token from
either `<meta name="csrf-token">` or the `XSRF-TOKEN` cookie. It exposes
`export-success`, `export-queued` and `export-error` events; custom renderers can
use the same controller directly:

```ts
import { useActions, useExports, useTable } from "@musing/inertia-table-vue";

const table = useTable(() => props.topics);
const actions = useActions(table);
const exports = useExports(table, actions);
```

Queued dispatches expose `queuedExport` with `dispatched`, `processing`, `ready`,
`failed` or `expired` state. Unless the export redirects after dispatch, the Vue
renderer polls a signed, actor-scoped status endpoint until the job reaches a
terminal state. The progress dialog can be dismissed without stopping that
polling; it opens again when the export becomes ready, fails or expires. A ready
status with a URL renders a download action. Applications using notifications or
a realtime channel can still call
`updateQueuedExport(status)`. An explicit `redirectAfterDispatch()` is followed
immediately and disables the built-in polling loop.

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

Musing Inertia Table translates this state to Spatie's query contract internally. Invalid columns, sorts, clauses, filter values and page sizes are ignored or replaced by safe defaults before the query executes.

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

The design and resource contract are described in
[docs/architecture.md](docs/architecture.md). Public compatibility guarantees
are documented in [docs/api-stability.md](docs/api-stability.md).

## License

Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md). Report
security issues through the process in [SECURITY.md](SECURITY.md).

The MIT License. See [LICENSE.md](LICENSE.md).
