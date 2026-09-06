# Upgrading Musing Inertia Table

## Resource schema v1 to v2

Schema v2 is the resource contract targeted for the `1.0` release. The PHP and
Vue packages must be upgraded together.

### Changes

- `schemaVersion` is now the literal `2`.
- The root resource adds `emptyState`, which is either a server-normalized empty
  state or `null`.
- `capabilities.hasEmptyState` reports whether the table declares an empty
  state, independently of whether it is currently visible.
- Each row may expose normalized DOM attributes at
  `_table.dataAttributes`.

Existing state, selection, action, view and export payloads retain their field
names and semantics. Saved View records use their own `state.schemaVersion: 2`;
that persistence version is intentionally independent from the table resource
version. Readers continue to normalize older persisted view state through the
current table declarations.

### Custom renderers

Update copied TypeScript types to `TableResource.schemaVersion: 2`. Render
`resource.emptyState` only when it is non-null, and forward row data attributes
to the `<tr>` element. Renderers that ignore both additive fields remain
functional after updating the schema literal.

Do not derive a genuine empty state from `results.total === 0`: that total may
be zero because of search or filters. The server-owned `emptyState` field is the
authoritative distinction.

## Queued exports

Queued export status responses now use `The export could not be completed.` for
dispatch and worker failures. Keep operational detail in `onFailure` callbacks
and application logging. New snapshots also capture the dispatch locale; deploy
the updated workers before allowing updated producers to enqueue exports.
