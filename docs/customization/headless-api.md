# Headless API

Compose the exported Vue composables when the application owns the table markup
or needs to integrate the behavior into another design system.

```ts
import {
    useActions,
    useExports,
    useStickyColumns,
    useTable,
    useViews,
} from "@musing/inertia-table-vue";

const table = useTable(() => props.topics);
const sticky = useStickyColumns(table);
const actions = useActions(table);
const exports = useExports(table, actions);
const views = useViews(table);
```

The resource remains authoritative. Do not derive searchability, selectability,
or action availability from visible DOM rows.

## `useTable`

Owns normalized client state and Inertia visits. It exposes search, sort,
filters, pagination, column visibility/order/widths, pinning, lazy filter option
loading, and navigation state.

```ts
table.setSearch("laravel");
table.setSort("created_at", "desc");
table.setFilter("status", ["published"], "in");
table.setPage(2);
```

## `useActions`

Owns explicit and all-matching selection, confirmation, managed action requests,
queued operation polling, and row-key resolution.

```ts
const actions = useActions(table, {
    rowKey: (topic) => topic.uuid,
});
```

Application endpoints receive the complete `TableSelection` descriptor and are
responsible for server-side resolution.

## `useExports`

Submits declared export requests, tracks queued exports, and exposes download or
failure state. Pass the same `useActions` instance so selected exports reuse the
current selection.

## `useViews`

Exposes the selected view, dirty state, and server-authorized view mutations.
Persistable state excludes transient page, cursor, and selection state.

## `useStickyColumns`

Computes logical pin groups, offsets, and styles from visible columns and
measured widths. Use it with semantic table cells if recreating the renderer.

## Supporting exports

The package also exports:

- `tableUrl`
- `formatSummaryValue`
- `setIconResolver`
- `setClauseSymbols`
- i18n helpers and English/Vietnamese catalogs
- selected UI primitives used by the default renderer
- the complete public TypeScript resource and state types

See the [Vue API reference](/reference/vue-api) for the export inventory.
