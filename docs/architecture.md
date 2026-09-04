# Musing Inertia Table — Architecture v0.1

Status: resource schema v2 implemented; public APIs are stabilizing for v1.0.

## Product boundary

Musing Inertia Table is a server-driven table framework for Laravel and Inertia.js. A PHP table definition is the source of truth for query capabilities and serialized UI metadata. The frontend owns interaction state and rendering, but it may only request operations explicitly declared by the server.

Spatie Laravel Query Builder v7 is the query execution engine. Musing Inertia Table owns the namespaced URL contract, table resource, state validation, action protocol, and frontend integrations.

## Design principles

1. The server is authoritative for searchable, sortable, filterable, selectable, and actionable capabilities.
2. A table definition is transport-agnostic. It must not know about Vue or shadcn-vue.
3. Query state and action state are separate subsystems.
4. Frontend behavior is headless. Renderers compose the headless API instead of reimplementing navigation and selection.
5. Every table has a stable name and isolated URL state, allowing multiple tables on one page.
6. The resource contract is versioned before adding convenience APIs.
7. v0.1 implements a small contract completely rather than exposing incomplete versions of advanced features.

## Layers

### 1. Laravel core

Responsibilities:

- table definitions and configuration;
- column, filter, and action definitions;
- authorization and capability serialization;
- request parsing and validation;
- translation to Spatie `AllowedSort` and `AllowedFilter` definitions;
- pagination and result transformation;
- versioned Inertia resource serialization.

The Laravel core must not serialize PHP callbacks or styling implementation details. Callbacks are evaluated server-side and only their results are serialized.

### 2. Frontend core

Responsibilities:

- consume and validate the resource shape;
- expose `useTable()` for search, sorting, filters, columns, and pagination;
- expose `useActions()` for selection, row actions, and bulk actions;
- expose `useViews()` for persisted table-state operations;
- generate namespaced URLs;
- perform Inertia partial reloads;
- preserve unrelated query parameters;
- coordinate loading, errors, and selection invalidation.

The frontend core contains no DOM and no shadcn-vue imports.

### 3. Vue shadcn renderer

Responsibilities:

- compose `useTable()` and `useActions()`;
- render source components based on shadcn-vue and Reka UI;
- expose stable package CSS hooks and documented Vue slots;
- use the consumer's shadcn CSS variables and Tailwind theme;
- provide accessible keyboard and screen-reader behavior.

The renderer must use actual SFC source components following shadcn-vue conventions. It must not implement a parallel CSS-only imitation of shadcn.

## Proposed package layout

```text
musing/inertia-table                 Laravel/PHP core
@musing/inertia-table                framework-neutral TypeScript core (future)
@musing/inertia-table-vue            Vue composables and shadcn renderer
```

For v0.1 the TypeScript core may live inside the Vue package, but its modules must remain free of Vue component and shadcn imports so it can be extracted without changing the resource contract.

## Table resource v2

```ts
type TableResource<T> = {
    schemaVersion: 2;
    name: string;
    columns: ColumnResource[];
    filters: FilterResource[];
    actions: ActionResource[];
    search: string[];
    capabilities: {
        searchable: boolean;
        selectable: boolean;
        paginated: boolean;
        hasSearch: boolean;
        hasFilters: boolean;
        hasActions: boolean;
        hasBulkActions: boolean;
        hasExports: boolean;
        hasToggleableColumns: boolean;
        hasStickableColumns: boolean;
        hasEmptyState: boolean;
    };
    state: TableState;
    results: PaginatedResults<T>;
    options: {
        debounceTime: number;
        perPage: number[];
        paginationType: "full" | "simple" | "cursor";
        reloadProps: string[];
        stickyHeader: boolean;
    };
    views: TableViewsResource | null;
    exports: ExportResource[];
    emptyState: EmptyStateResource | null;
};
```

### Column resource

```ts
type ColumnResource = {
    attribute: string;
    header: string;
    type:
        | "text"
        | "numeric"
        | "badge"
        | "boolean"
        | "date"
        | "datetime"
        | "image"
        | "action";
    sortable: boolean;
    toggleable: boolean;
    visibleByDefault: boolean;
    stickable: boolean;
    sticky: boolean;
    alignment: "left" | "center" | "right";
    wrap: boolean;
    truncate: number | null;
    tooltip: string | null;
    headerClass: string | null;
    cellClass: string | null;
    meta: Record<string, unknown>;
    asDropdown?: boolean;
};
```

### Empty-state and row metadata

```ts
type EmptyStateResource = {
    title: string;
    message: string | null;
    icon: string | false | null;
    actions: EmptyStateActionResource[];
    dataAttributes: Record<string, string | number | boolean | null>;
    meta: Record<string, unknown>;
};

type RowMetadata = {
    key: string | number;
    selectable: boolean;
    url: TableUrl | null;
    columns: Record<string, TableUrl>;
    cells: Record<string, Record<string, unknown>>;
    actions: ActionResource[];
    dataAttributes: Record<string, string | number | boolean | null>;
};
```

The server emits an empty-state resource only after the filtered result is empty
and the normalized base query has no rows. Filtered no-results therefore cannot
accidentally show a create-first-record call to action. Row and empty-state data
attributes are normalized to `data-*`, accept scalar or null values only, and
cannot replace package-owned row state.

Searchability belongs to table query configuration, not presentation state. A
column helper may opt an attribute into search, while the Table `$search`
property can explicitly override the resolved allowlist. The serialized
resource exposes both that allowlist and convenient capabilities.

### Anonymous table boundary

`Table::build()` returns an `AnonymousTable` backed by either a model class or a
cloned Eloquent builder. It reuses the same column, filter, state normalization,
query allowlist and resource v2 pipeline as a dedicated class. Builder callbacks
may mutate the isolated Spatie query builder or return a replacement; model
transforms must return arrays.

Anonymous tables do not expose row/bulk actions, exports or Saved Views because
those protocols require a stable class reference when a later signed request is
resolved. The `make:inertia-table` generator is the supported migration path
once a table needs those features. Disabling pagination changes
`capabilities.paginated` to false, returns a one-page result envelope and makes
the renderer and headless page controls inert.

Paginated tables select `full`, `simple`, or `cursor` mode globally or per table.
Full pagination exposes exact totals and first/last navigation. Simple pagination
omits totals but retains a numeric page. Cursor pagination stores an opaque
cursor instead of a page in the table's URL namespace and requires a plain,
non-null base-table sort. A qualified primary-key order is appended as a stable
tie-breaker. Relationship and expression sorts are rejected in cursor mode.

### Filter resource

```ts
type FilterResource = {
    attribute: string;
    label: string;
    type: "text" | "set" | "numeric" | "date" | "boolean";
    clauses: FilterClause[];
    options: Array<{ value: string | number | boolean; label: string }>;
    meta: Record<string, unknown>;
};

type FilterState = {
    enabled: boolean;
    clause: string;
    value: unknown;
};
```

Every declared filter has a state entry. Inactive filters serialize with
`enabled: false`; enabled filters are the only entries compiled into Spatie
Query Builder callbacks. Each filter type owns validation and clause-specific
query behavior. A declared dot path is split into an Eloquent relationship path
and related attribute, then applied through nested `whereHas`; the client cannot
introduce a relationship path that is absent from the table definition. Direct
attributes are qualified against their model table.

### Action resource

```ts
type ActionResource = {
    key: string;
    label: string;
    scope: "row" | "bulk" | "both";
    authorized: boolean;
    queued?: true;
    variant: "default" | "destructive";
    confirmation: null | {
        title: string | [string, string, string?];
        message: string | [string, string, string?];
        confirmLabel: string;
        cancelLabel: string;
    };
    endpoint: null | {
        method: "get" | "post" | "put" | "patch" | "delete";
        url: string;
    };
    meta: Record<string, unknown>;
};
```

Actions are declared by the table and executed through `useActions()`. An action may point to an application-owned endpoint, define a server-side handler, or omit both and emit a frontend custom action. Server-side handlers serialize as signed internal POST endpoints and recheck action scope and availability before execution. Request-level authorization is separate from per-model row availability. Handler closures remain server-only and are never serialized.

Managed actions have a once-per-request lifecycle: `before`, handler execution, then `after`. Per-model handlers iterate by primary key with a configurable chunk size and skip unselectable, unauthorized, disabled, or hidden models during bulk execution. Selection handlers deliberately expose the normalized query for set-based work, so they own any additional per-model eligibility constraints that cannot be represented by the table's selectable query.

Confirmation placeholders are resolved from the pending action context on the frontend. `:count` uses the exact explicit selection size or selectable matching rows minus exclusions; scalar row attributes such as `:name` resolve from serialized row data. Confirmation titles and messages may declare singular, plural, and all-matching variants.

### Table state

```ts
type TableState = {
    search: string;
    sort: string | null;
    filters: Record<string, FilterState>;
    columns: Record<string, boolean>;
    page: number;
    perPage: number;
    view?: string | number | null;
    pinnedColumns: { left: string[]; right: string[] };
    cursor?: string | null;
};
```

Selection is intentionally not serialized into the URL. It is ephemeral frontend action state and is cleared when the result set identity changes.

### Saved views

Saved views are an opt-in persistence layer over normalized table state. A view
stores schema version, sort, filters, column visibility, pinned-column metadata,
page size and optionally search. Page number and selection are never persisted.
On read, state is normalized through the current table declarations so removed
columns, filters, clauses and page sizes cannot be restored from stale records.

The resolution order is explicit URL state, selected view, scoped default view,
then table defaults. View identity is part of the table namespace, while unrelated
navigation state remains intact. The default renderer exposes the current view,
dirty state and authorized mutations; `useViews()` provides the same behavior to
custom renderers.

Persistence uses a publishable `table_views` migration. Context hashes isolate
the table class, optional table name and recursively normalized application
attributes. Scope hashes additionally isolate the owning user. User-scoped views
may be shared read-only with other users; `scopeUser(false)` creates global views.
All mutations use signed CSRF-protected routes, rerun server authorization and
require an optimistic `lock_version` to prevent lost updates.

### Selection resolver

The frontend selection descriptor is resolved to a typed PHP `Selection`. Explicit keys are constrained by the table's base query and selectable query. An all-results selection rebuilds search and filter state only through declared columns and filters, applies the selectable query, then applies the `except` keys. The paginated resource includes `results.selectableTotal`, while every row includes `_table.selectable`. The resulting query never trusts a raw client column or clause.

`Action::handle()` iterates the resolved query by primary key in chunks and invokes the handler for each model. `Action::handleSelection()` invokes a handler once with the `Selection`, allowing set-based queries without loading every selected key or model into memory.

Queued bulk actions capture the normalized `Selection` plus table/action
identity, a deterministic definition fingerprint, actor, locale and scalar
application scope. Queue payloads never contain a live request, table, builder,
model or definition closure. Workers restore context, re-resolve the current
definition, rerun authorization and availability checks, and reject stale
definitions before executing. Per-model execution writes progress only at chunk
boundaries; set-based handlers expose lifecycle state without invented row
progress. A cache lock prevents concurrent delivery of the same operation, while
the client idempotency key deduplicates repeated dispatch requests.

Status resources are signed and scoped to the current actor and application
attributes. The browser owns polling independently per table instance, clears
selection only after a `202` snapshot response, and performs a targeted Inertia
reload after completion. Dismissing the status dialog does not dispose polling;
unmounting the table does.

### Export contract

Exports are server-declared capabilities with a stable key, label, filename,
format, row scope, metadata and request-level authorization. Only authorized
definitions are serialized, and every download runs through a signed,
CSRF-protected POST endpoint that resolves the table class and authorization
again.

The three synchronous row scopes deliberately reuse existing query boundaries:

- `all` uses the table base query;
- `filtered` rebuilds normalized search, filters and sort without pagination;
- `selected` uses the typed `Selection`, including all-matching exclusions and
  selectable scopes.

Columns resolve exported values server-side. `exportAs()` receives the rendered
value and Eloquent model, `dontExport()` removes a column, and adapter-only format
and metadata stay out of the frontend resource. Action columns are excluded by
default. Column visibility affects an export only when the definition opts into
`visibleColumnsOnly()`.

The native CSV adapter streams an Eloquent cursor to `php://output` for downloads
and writes queued files through a temporary stream, keeping PHP memory bounded in
both paths. It owns UTF-8 encoding, CSV escaping, scalar normalization and
spreadsheet-formula protection. Other formats implement the `Exporter` contract.
The Laravel Excel adapter is optional and is loaded only after its dependency is
detected, so installing the base package does not pull spreadsheet or PDF code.

Queued exports snapshot only the table class, export key, normalized state,
normalized selection descriptor, actor identifier, scalar scope attributes and
storage settings. The queue payload does not serialize the request, table, query
builder, export definition or callbacks. A worker restores the actor and optional
application context, resolves the current table definition, rejects missing or
materially changed definitions, reruns authorization, then reconstructs the same
query and columns used by synchronous exports.

Dispatch is idempotent per table, export, actor, scope attributes and client
idempotency key. Status is stored as `dispatched`, `processing`, `ready`, `failed`
or `expired`; completed files receive an application-defined delivery URL and are
deleted by a delayed cleanup job. Partial files are deleted on failure. Ready and
failure callbacks, job chaining and post-dispatch redirects are definition-time
hooks and are resolved around the serializable snapshot rather than embedded in
it. The Vue composable polls the signed status endpoint until a terminal state.
Applications using notifications or realtime updates may feed the same resource
back through `updateQueuedExport()`.

## URL contract

Each table owns one namespace:

```text
table[topics][search]=laravel
table[topics][sort]=-created_at
table[topics][filters][status][enabled]=1
table[topics][filters][status][clause]=equals
table[topics][filters][status][value]=featured
table[topics][columns][created_at]=0
table[topics][page]=2
table[topics][perPage]=30
```

Cursor-mode tables replace `page` with `table[topics][cursor]=<opaque-token>`.
Search, sort, filter, per-page, and Saved View changes clear that token before
navigation because each changes the identity or ordering of the result set.

Unknown attributes, clauses, sorts, columns, actions, and per-page values are ignored or replaced with server defaults. Raw request values must never be passed directly to `orderBy`, filter callbacks, or action handlers.

## Spatie Query Builder integration

- sortable columns compile to `AllowedSort` instances;
- filters compile to `AllowedFilter` instances;
- global search compiles to one callback filter over an explicit attribute allowlist;
- custom sorts and filters are supplied as Spatie-compatible implementations or server callbacks;
- Musing Inertia Table converts namespaced table state to an isolated Spatie request internally;
- the application's global request query is not mutated;
- pagination parameters are validated by Musing Inertia Table before reaching the query.

Relationship search and filters use existence subqueries, avoiding duplicate base
models for to-many matches. Sortable to-one paths delegate to the optional Power
Joins adapter with a left join. Sortable to-many paths use correlated `MIN`/`MAX`
aggregates to give one deterministic sort value per model. `sortUsing()` remains
the dependency-free escape hatch, while `sortUsingMap()` and
`sortUsingPriority()` provide allowlisted display and priority ordering.

`Table::withQueryBuilder()` receives the isolated package `QueryBuilder` for both
stateful and all-row queries. Consequently results, explicit/all-matching
selections, selectable totals, and synchronous/queued exports share the same
application customization. If the base query or hook introduces joins, Musing Inertia Table
selects base-model columns and applies a distinct qualified primary key before
pagination or iteration to stabilize model identity and counts.

Spatie Query Builder does not own column visibility, table naming, action execution, selection, Inertia partial reloads, or the public resource schema.

## Vue public API

```ts
const table = useTable(resource);
const sticky = useStickyColumns(table);
const actions = useActions(table);
const views = useViews(table);
const exports = useExports(table, actions);
```

`useTable()` owns resolved state and navigation operations. `useStickyColumns()`
owns visible pin groups, measured widths, logical offsets and edge metadata.
`useActions()` owns explicit keys, all-results selection with exclusions,
current-page Shift-click ranges, row/bulk action availability, confirmation,
execution, and pending state. `useViews()` owns view switching, normalized
persistence payloads, dirty comparison and authorized mutations. `useExports()`
owns signed download and queued-dispatch requests, pending/error/queued state and
browser downloads while preserving selection. The header checkbox selects every
selectable row matching the normalized search/filter state across pagination
immediately; it never stops at the current page. Its three states use
`selectableTotal`, and ranges skip unselectable rows.

Sticky state remains presentation/navigation state and is excluded from the
selection descriptor. The server normalizes pinned attributes through current
column declarations, restores permanent `sticky()` columns, and persists the
result in Saved Views. The default renderer measures header cells once for both
header and body offsets, recalculates on resize or visibility changes, uses
logical insets for RTL, and keeps sticky backgrounds and edge shadows opaque.

The default `<DataTable>` exposes these scopes through documented slots:

- `topbar`, `beforeSearch`, `afterSearch`;
- `filters` and dynamic filter input slots;
- `table`, `thead`, `tbody`;
- dynamic column header and cell slots;
- `loading`, `emptyState`, `footer`;
- row actions and bulk actions.

Slot names use one documented convention and are covered by contract tests.

## shadcn-vue strategy

The renderer will vendor only the shadcn-vue source components it directly needs. Each component remains an SFC in `components/ui/<component>`, preserving upstream prop and emit behavior where practical.

Package-specific composition belongs in `components/table`, not `components/ui`. For example, `TablePagination.vue` may compose shadcn `Button` and `Select`, but it is not itself a shadcn primitive.

Consumers must not need aliases such as `@/components/ui`. Required peer dependencies and Tailwind source scanning instructions are documented. Stable `tb-*` classes and documented data attributes are added for overrides without pretending they are upstream shadcn APIs.

## v0.1 scope

Included:

- text, numeric, boolean, date, and action columns;
- allowlisted single-column sorting;
- allowlisted global search;
- text, boolean, and select filters with a default clause;
- full and simple pagination;
- cursor pagination with deterministic base-table sorting;
- column visibility;
- explicit row selection and all-results selection across pagination;
- server-declared row and bulk actions;
- typed server-side selection resolution and managed action handlers;
- synchronous and queued CSV exports with optional exporter adapters;
- allowlisted nested relationship search, filtering and sorting;
- shared Spatie query customization across results, selections and exports;
- sticky headers and user-toggleable or permanent columns with Saved View state;
- multiple named tables and partial reloads;
- headless Vue composables and one shadcn-vue renderer;
- localization-ready labels;
- contract, query, URL, composable, and renderer tests.

Deferred:

- React renderer;
- virtualized rows;
- persisted selection across search/filter changes.

## Required test boundaries

1. PHP contract tests assert exact resource serialization.
2. Query tests assert that unknown sort/filter/search inputs cannot affect SQL.
3. URL tests cover multiple tables and preservation of unrelated parameters.
4. Composable tests cover state transitions independently of the renderer.
5. Renderer tests verify slots and accessibility, not query behavior.
6. Consumer fixture tests install the built package into a minimal Laravel/Inertia/Vue application.

## Migration from the spike

The current branch remains available as evidence and a UI experiment. Implementation should restart from the Laravel skeleton while selectively porting tested pieces:

- retain table naming, namespaced URL parsing, and Spatie allowlist compilation after adapting them to this contract;
- replace `useDataTable()` with separate `useTable()` and `useActions()` APIs;
- replace the current resource shape before it is published;
- remove application-specific bulk action orchestration from the Quote page;
- discard the current shadcn imitation and reintroduce UI primitives from verified shadcn-vue source;
- integrate Quote only after package contract tests pass.

No history rewrite or destructive reset is implied by this document.
