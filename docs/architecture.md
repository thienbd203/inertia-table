# Musing Inertia Table — Architecture v0.1

Status: proposal. The current implementation is a spike and does not define the public API.

## Product boundary

Musing Inertia Table is a server-driven table framework for Laravel and Inertia.js. A PHP table definition is the source of truth for query capabilities and serialized UI metadata. The frontend owns interaction state and rendering, but it may only request operations explicitly declared by the server.

Spatie Laravel Query Builder v7 is the query execution engine. Toolbelt owns the namespaced URL contract, table resource, state validation, action protocol, and frontend integrations.

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
- generate namespaced URLs;
- perform Inertia partial reloads;
- preserve unrelated query parameters;
- coordinate loading, errors, and selection invalidation.

The frontend core contains no DOM and no shadcn-vue imports.

### 3. Vue shadcn renderer

Responsibilities:

- compose `useTable()` and `useActions()`;
- render source components based on shadcn-vue and Reka UI;
- expose stable Toolbelt CSS hooks and documented Vue slots;
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

## Table resource v1

```ts
type TableResource<T> = {
    schemaVersion: 1;
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
        hasToggleableColumns: boolean;
    };
    state: TableState;
    results: PaginatedResults<T>;
    options: {
        debounceTime: number;
        perPage: number[];
        reloadProps: string[];
    };
};
```

### Column resource

```ts
type ColumnResource = {
    attribute: string;
    header: string;
    type: "text" | "numeric" | "badge" | "boolean" | "date" | "datetime" | "image" | "action";
    sortable: boolean;
    toggleable: boolean;
    visibleByDefault: boolean;
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

Searchability belongs to table query configuration, not presentation state. A
column helper may opt an attribute into search, while the Table `$search`
property can explicitly override the resolved allowlist. The serialized
resource exposes both that allowlist and convenient capabilities.

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
query behavior.

### Action resource

```ts
type ActionResource = {
    key: string;
    label: string;
    scope: "row" | "bulk" | "both";
    authorized: boolean;
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
};
```

Selection is intentionally not serialized into the URL. It is ephemeral frontend action state and is cleared when the result set identity changes.

### Selection resolver

The frontend selection descriptor is resolved to a typed PHP `Selection`. Explicit keys are constrained by the table's base query and selectable query. An all-results selection rebuilds search and filter state only through declared columns and filters, applies the selectable query, then applies the `except` keys. The paginated resource includes `results.selectableTotal`, while every row includes `_table.selectable`. The resulting query never trusts a raw client column or clause.

`Action::handle()` iterates the resolved query by primary key in chunks and invokes the handler for each model. `Action::handleSelection()` invokes a handler once with the `Selection`, allowing set-based queries without loading every selected key or model into memory.

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

Unknown attributes, clauses, sorts, columns, actions, and per-page values are ignored or replaced with server defaults. Raw request values must never be passed directly to `orderBy`, filter callbacks, or action handlers.

## Spatie Query Builder integration

- sortable columns compile to `AllowedSort` instances;
- filters compile to `AllowedFilter` instances;
- global search compiles to one callback filter over an explicit attribute allowlist;
- custom sorts and filters are supplied as Spatie-compatible implementations or server callbacks;
- Toolbelt converts namespaced table state to an isolated Spatie request internally;
- the application's global request query is not mutated;
- pagination parameters are validated by Toolbelt before reaching the query.

Spatie Query Builder does not own column visibility, table naming, action execution, selection, Inertia partial reloads, or the public resource schema.

## Vue public API

```ts
const table = useTable(resource);
const actions = useActions(table);
```

`useTable()` owns resolved state and navigation operations. `useActions()` owns explicit keys, all-results selection with exclusions, current-page Shift-click ranges, row/bulk action availability, confirmation, execution, and pending state. The header checkbox selects every selectable row matching the normalized search/filter state across pagination immediately; it never stops at the current page. Its three states use `selectableTotal`, and ranges skip unselectable rows.

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

Consumers must not need aliases such as `@/components/ui`. Required peer dependencies and Tailwind source scanning instructions are documented. Stable `tb-*` classes or `data-toolbelt-*` attributes are added for overrides without pretending they are upstream shadcn APIs.

## v0.1 scope

Included:

- text, numeric, boolean, date, and action columns;
- allowlisted single-column sorting;
- allowlisted global search;
- text, boolean, and select filters with a default clause;
- full and simple pagination;
- column visibility;
- explicit row selection and all-results selection across pagination;
- server-declared row and bulk actions;
- typed server-side selection resolution and managed action handlers;
- multiple named tables and partial reloads;
- headless Vue composables and one shadcn-vue renderer;
- localization-ready labels;
- contract, query, URL, composable, and renderer tests.

Deferred:

- exports and queued exports;
- saved views/bookmarks;
- relationship sorting helpers beyond custom Spatie sorts;
- cursor pagination;
- React renderer;
- sticky columns and virtualized rows;
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
