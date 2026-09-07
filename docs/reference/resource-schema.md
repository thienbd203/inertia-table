# Resource schema

The Laravel and Vue packages communicate through table resource schema version
2. The root object is represented by `TableResource<T>`.

## Root fields

| Field | Meaning |
| --- | --- |
| `schemaVersion` | Always `2` for this contract |
| `name` | Table identity and URL namespace |
| `columns` | Declared column resources |
| `filters` | Declared filter resources |
| `actions` | Resolved bulk actions; row actions also live in row metadata |
| `search` | Resolved searchable attributes |
| `capabilities` | Renderer feature flags |
| `state` | Normalized current URL/view/default state |
| `results` | Rows and pagination envelope |
| `options` | Interaction and pagination options |
| `views` | Saved Views resource or `null` |
| `exports` | Authorized export definitions |
| `emptyState` | Genuine base-table empty state or `null` |
| `summaries` | Values keyed by column attribute |

## State

`TableState` contains search, sort, filters, column visibility, page size,
pagination position, selected view, pin groups, column order, and widths.

Filter state has a stable shape:

```ts
type TableFilterState = {
    enabled: boolean;
    clause: string;
    value: unknown;
};
```

The server normalizes every field against its definition. Custom renderers
should submit state through `tableUrl` or `useTable` rather than constructing an
alternate schema.

## Result envelope

All pagination modes use one `TableResults<T>` shape. Fields unavailable in a
mode are `null` or optional:

- full pagination provides current/last page, links, and total;
- simple pagination provides page and previous/next flags;
- cursor pagination provides opaque previous/next cursors;
- `selectableTotal` appears when exact all-matching selection is available.

Each Laravel row includes `_table` metadata for key, selectability, navigation,
cell metadata, resolved row actions, and safe data attributes.

## Additive fields

Optional fields may be added in a minor release. Custom renderers should ignore
unknown keys and handle documented optional fields being absent during a rolling
frontend/backend upgrade.

Examples include `lazy`/`lazyLoaded` on set filters and capability flags added
after the schema was introduced.

## Persistence contracts

Saved View state, queued action/export snapshots, and database migrations have
their own versions. A root resource version change does not automatically
invalidate persisted views or queued work.

Read [API stability](/internals/api-stability) before implementing a custom
renderer that stores resource data.
