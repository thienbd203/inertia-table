# Rendering and slots

Use the default renderer for table structure and slots for targeted application
content. The server resource remains authoritative even when a cell or control
is replaced.

## Event typing

Type the resource as `TableResource<YourRow>` to infer row-click and row-key
arguments. `row-click` receives `(item, column)` and `column` may be null.

| Event | Arguments |
| --- | --- |
| `custom-action` | `action`, `keys`, `onFinish`, `selection` |
| `action-success` | `action`, `keys`, `selection` |
| `action-error` | `action`, `keys`, `error: unknown`, `selection` |
| `action-queued`, `action-progress` | `action`, `QueuedActionStatus`, `selection` |
| `export-success` | `TableExport` |
| `export-queued` | `TableExport`, `QueuedExportStatus` |
| `export-error` | `TableExport`, `Error` |

Use the public package's `TableAction`, `TableKey`, `TableSelection`,
`QueuedActionStatus`, `QueuedExportStatus` and `TableExport` types for named
handlers. Narrow action errors before reading properties; export errors already
use `Error`. The custom-action completion callback takes no arguments.

Stricter slot types can reveal assumptions previously hidden by `any`: check
nullable action rows and image fallbacks, and narrow cell/filter values. For
programmatic mounting, specify the component generic when inference is lost,
for example `mount(DataTable<Topic>, ...)` in Vue Test Utils.

## Custom cells

`cell(...)` slots infer `item` from the resource's row type. Their `column` is
`TableColumn` and their `value` is `unknown`, since PHP mapping can change a
cell's value independently of the row attribute. Narrow `value` before using
it as a string or number. `header(...)` slots expose a typed `column` (its title
is `column.header`). Both retain the shared table/actions/exports/views scope.
`action(...)` exposes `item: T | null` because bulk actions have no row,
`selectedItems: T[]`, selection/count, and a zero-argument `execute()` callback.
Check `item` before reading row fields. `filter(...)` exposes optional applied
`state`, an `unknown` draft value, `update(value?)`, `close()` and
`setDisplayValue(string | null)`. Shared scope remains available in both.
`summary(...)` exposes `value: unknown`, `formatted: string`, its column and
optional summary definition. `image(...)` exposes image metadata, while
`image-fallback(...)` allows that metadata to be null. Layout slots such as
`topbar`, `thead`, `tbody`, `footer`, `loading` and `emptyState` receive the shared
scope. `confirmation` adds the pending action, and `queuedAction` adds a nullable
status (it can also render when an error exists without a status).
Unknown extension slot names retain a permissive fallback.

```vue
<DataTable :resource="topics">
    <template #cell(name)="{ item }">
        <strong>{{ item.name }}</strong>
    </template>

    <template #emptyState>
        <p class="py-10 text-center text-muted-foreground">
            No topics found.
        </p>
    </template>
</DataTable>
```

## Slot inventory

Layout slots:

- `topbar`
- `beforeSearch` and `afterSearch`
- `beforeActions` and `afterActions`
- `filters`
- `table`, `thead`, and `tbody`
- `summaryFooter`
- `footer`
- `loading`
- `emptyState`
- `confirmation`
- `queuedAction`

Attribute slots:

- `cell(<attribute>)`
- `header(<attribute>)`
- `summary(<attribute>)`
- `filter(<attribute>)`
- `image(<attribute>)`
- `image-fallback(<attribute>)`
- `action(<key>)`

Use attribute slots when only one definition needs custom rendering. Replacing
the structural table slots makes the application responsible for accessibility
and layout behavior inside that region.

## Application-owned filter control

Use `filter(<attribute>)` for a non-Eloquent source, option creation, or a custom
picker. First declare the stored value on the server:

```php
NumericFilter::make('source_id', 'Source')->withoutClause();
```

Then render the control:

```vue
<DataTable :resource="quotes">
    <template
        #filter(source_id)="{
            value,
            update,
            setDisplayValue,
            close,
        }"
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

The package owns the value and URL state. The application owns loading, search,
pagination, option creation, and the external endpoint.

## Icon resolver

Icon names are library-agnostic. Register a resolver once:

```ts
import { Pencil, Trash2 } from "@lucide/vue";
import { setIconResolver } from "@musing/inertia-table-vue";

setIconResolver((name) => ({ Pencil, Trash2 })[name]);
```

The resolver receives both the icon name and its context. A table may instead
provide its own `iconResolver` prop.

## Row identity

Laravel-generated resources include stable Eloquent key metadata. For an
application-owned resource, provide the identity explicitly:

```vue
<DataTable
    :resource="topics"
    :row-key="(topic) => topic.uuid"
/>
```

The headless equivalent is
`useActions(table, { rowKey: (topic) => topic.uuid })`.

For a fully application-owned renderer, see the [headless API](/customization/headless-api).
