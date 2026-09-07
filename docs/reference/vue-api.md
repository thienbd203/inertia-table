# Vue API

All entries on this page are exported by `@musing/inertia-table-vue`.

## Component

### `DataTable`

Required prop:

| Prop | Type |
| --- | --- |
| `resource` | `TableResource<T>` |

Optional props:

- `searchPlaceholder`
- `iconResolver`
- `locale`
- `messages`
- `rowKey`

Events:

- `custom-action`
- `action-success`, `action-error`, `action-queued`, `action-progress`
- `export-success`, `export-queued`, `export-error`
- `row-click`

See [rendering and slots](/customization/rendering-and-slots) for slot names and
examples.

## Composables

| Export | Responsibility |
| --- | --- |
| `useTable` | State, URL visits, filters, sorting, pagination, and column layout |
| `useActions` | Selection, action execution, confirmation, and queued action state |
| `useExports` | Export submission, polling, and download state |
| `useViews` | Saved View selection, dirty state, and mutations |
| `useStickyColumns` | Pin groups, offsets, and cell styles |

These APIs are designed to use the same reactive `TableResource` instance. See
[headless API](/customization/headless-api).

## Helpers

- `tableUrl`
- `formatSummaryValue`
- `setIconResolver`
- `setClauseSymbols`
- `createInertiaTable`
- `createTableI18n`
- `provideTableI18n`
- `useTableI18n`
- `en` and `vi` message catalogs

## UI primitives

The package exports a small set of primitives for headless implementations:

- `UiButton`
- `UiCheckbox`
- `UiInput`
- `UiNativeSelect`, `UiNativeSelectOptGroup`, `UiNativeSelectOption`
- `UiTable`, `UiTableBody`, `UiTableCell`, `UiTableHead`, `UiTableHeader`,
  `UiTableRow`

Other components below `resources/js/components/ui` are internal.

## Types

Resource and state:

- `TableResource`, `TableResults`, `TableState`, `TableItem`, `TableKey`
- `TableColumn`, `TableFilter`, `TableFilterOption`, `TableFilterState`
- `TableAction`, `TableSelection`, `TableExport`
- `TableView`, `TableViewState`, `TableViewsResource`
- `TableEmptyState`, `TableEmptyStateAction`
- `PaginationLink`, `TableOptions`, `DataTableOptions`

Queued operations:

- `QueuedActionStatus`
- `QueuedExportStatus`

Customization:

- `IconResolver`, `StickySide`, `UseStickyColumns`
- `InertiaTableI18nOptions`, `TableI18n`, `TableMessageKey`,
  `TableMessageOverrides`, `TableMessageParams`, `TableMessages`

The serialized field reference is in [resource schema](/reference/resource-schema).
