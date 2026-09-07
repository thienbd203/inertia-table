# PHP API

This page is an inventory of the primary public fluent API. Guides explain when
to use each capability.

## `Table`

### Construction and resolution

| Method | Purpose |
| --- | --- |
| `TableClass::make()` | Resolve a dedicated table through Laravel's container |
| `Table::build(...)` | Create an anonymous read-only table |
| `resolve(?Request $request = null)` | Resolve a typed `TableResource` |
| `toArray()` | Resolve the table for Laravel/Inertia serialization |
| `name()` | Return the explicit or class-derived table name |

### Definition methods

Dedicated tables implement `query()` and `columns()`. They may override
`filters()`, `actions()`, `exports()`, `views()`, and `emptyState()`.

### Runtime configuration

- `reloadProps(array|string $props)`
- `stickyHeader(bool $sticky = true)`
- `stickyFooter(bool $sticky = true)`
- `stickyBackdropFilter(bool $enabled = true)`
- `columnResizing(bool $enabled = true)`
- `columnReordering(bool $enabled = true)`
- `paginationType(PaginationType $type)`

### Extension points

- `selectableQuery(Builder $query): Builder`
- `isSelectable(Model $model): bool`
- `dataAttributesForModel(Model $model, array $data): array`
- protected `withQueryBuilder(QueryBuilder $query): QueryBuilder`

Methods such as `queryForState()`, `queryForSelection()`, `columnsForExport()`,
and `summariesForQuery()` are public server integration points. Prefer the
higher-level `Selection` and export APIs unless implementing package-level
integration.

## Columns

All content columns inherit the common API from `Column`:

| Group | Methods |
| --- | --- |
| Query | `searchable()`, `notSearchable()`, `sortable()`, `notSortable()`, `sortUsing()`, `sortUsingMap()`, `sortUsingPriority()` |
| Visibility | `toggleable()`, `notToggleable()`, `visible()` |
| Layout | `width()`, `minWidth()`, `maxWidth()`, `resizable()`, `reorderable()`, `stickable()`, `sticky()` |
| Text | `header()`, `align()`, alignment helpers, `wrap()`, `truncate()`, `tooltip()` |
| Styling | `headerClass()`, `cellClass()`, `meta()` |
| Presentation | `mapAs()`, `url()`, `image()` |
| Summary | `summary()`, `summaryUsing()`, `summaryFormat()` |
| Export | `exportAs()`, `exportable()`, `dontExport()`, `exportFormat()`, `exportMeta()` |

Built-in classes are `TextColumn`, `NumberColumn`, `NumericColumn`,
`BadgeColumn`, `BooleanColumn`, `DateColumn`, `DateTimeColumn`, `ImageColumn`,
and `ActionColumn`.

## Filters

All filters support:

- `make(string $attribute, ?string $label = null)`
- `clauses(array $clauses)`
- `clauseValueKinds(array $valueKinds)`
- `default(mixed $value, Clause|string|null $clause = null)`
- `withoutClause()`
- `compactDisplay(string $label)`
- `nullable(bool $nullable = true)`
- `meta(array $meta)`

`SetFilter` adds:

- `options(array $options)`
- `pluckOptionsFromModel(string $model, string $label = 'name', ?string $value = null)`
- `lazy(bool $lazy = true)`
- `multiple(bool $multiple = true)`
- `applyUsing(Closure $callback)`

`SelectFilter` is a deprecated alias. Built-in filters are covered in
[search and filters](/guide/search-and-filters).

## `Action`

Create an action with `Action::make($key, $label)`.

| Group | Methods |
| --- | --- |
| Scope | `row()`, `bulk()`, `rowAndBulk()` |
| Availability | `authorize()`, `authorized()`, `disabled()`, `hidden()`, `disabledAndHidden()` |
| Presentation | `destructive()`, `icon()`, `hideLabel()`, `tooltip()`, `buttonClass()`, `disabledTooltip()`, `meta()` |
| Execution | `endpoint()`, `handle()`, `handleSelection()`, `before()`, `after()`, `chunkSize()` |
| Confirmation | `confirm()` |
| Queue | `queue()`, `scopeAttributes()`, `context()`, `middleware()`, `tags()`, `chain()`, `redirectAfterDispatch()`, `onCompleted()`, `onFailure()`, `failureMessage()` |

See [actions](/features/actions) and [queues](/features/queues).

## `Export`

Create an export with `Export::make($key, $label, $type)`.

| Group | Methods |
| --- | --- |
| Identity | `label()`, `filename()`, `type()`, `meta()` |
| Scope | `allRows()`, `filtered()`, `selected()` |
| Columns | `visibleColumnsOnly()`, `visibleColumnLayout()`, `withSummaries()` |
| Query | `chunkSize()`, `modifyQueryUsing()` |
| Authorization | `authorize()` |
| Queue | `queue()`, `scopeAttributes()`, `context()`, `redirectAfterDispatch()`, `deliveryUrlUsing()`, `onReady()`, `onFailure()`, `chain()` |

## `Views`

Create a definition with `Views::make()`.

- `scopeUser()` and `userResolver()`
- `attributes()` and `scopeTableName()`
- `modelClass()`
- `includeSearch()`
- `authorizeCreate()`, `authorizeUpdate()`, `authorizeDelete()`,
  `authorizeShare()`, `authorizeDefault()`

## `Url`

Build navigation metadata with `Url::make()` and:

- `to()`
- `route()`
- `signedRoute()`
- `temporarySignedRoute()`
- `preserveScroll()` and `preserveState()`
- `openInNewTab()`
- `asDownload()`
- `disabled()` and `hidden()`

## Enums and contracts

Public enums include `Clause`, `PaginationType`, `SortDirection`, `Variant`,
`ColumnAlignment`, `ImagePosition`, `ImageSize`, `SummaryAggregate`, and
`ExportScope`.

Integration contracts include `ActionContext`, `ExportContext`, `Exporter`, and
`RelationshipSorter`.
