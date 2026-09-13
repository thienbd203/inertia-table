# Headless API

Compose the exported Vue composables when the application owns the table markup
or needs to integrate the behavior into another design system.

## Minimal searchable table

Start with the Laravel model, table and route from
[getting started](/guide/getting-started). This example expects a `topics`
Inertia prop with `id`, `name` and `status` on each row, a searchable resource,
and a declared sortable `name` column. Keep the table name equal to `topics`.

The following is the actual component compiled and rendered by the packed
consumer check:

<<< ../../tests/Consumer/app/Headless.vue

Pass a getter to `useTable` so it receives replacement resources after Inertia
visits. Destructure its refs at setup scope so Vue unwraps them in the template.
Search is debounced, the name button changes the server sort, and an empty
response renders the empty row. The markup displays only the current result
page; add pagination controls before using it for a larger dataset.

Run `npm run test:consumer` and then `node tests/Consumer/serve.mjs` in the
package checkout. Open the printed `/headless` URL; append `?csr=1` for client
rendering. Search `Beta`, clear it, and use the name button to change order.
See the [fixture instructions](https://github.com/thienbd203/inertia-table/tree/master/tests/Consumer).
The automated check covers types, builds, SSR and real Laravel partial search
responses; browser interaction and hydration still require the manual check.

## Compose additional behavior

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
