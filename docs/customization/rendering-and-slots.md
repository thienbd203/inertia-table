# Rendering and slots

Use the default renderer for table structure and slots for targeted application
content. The server resource remains authoritative even when a cell or control
is replaced.

## Custom cells

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
