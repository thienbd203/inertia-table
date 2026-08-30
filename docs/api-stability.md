# API stability

Musing Inertia Table follows semantic versioning. These guarantees apply from
the `1.0.0` release; pre-release builds may still change when the upgrade guide
calls out the migration.

## Stable public surface

The following APIs are public:

- documented PHP classes, enums, constructors, fluent methods, and the
  documented `Table` extension points;
- `Table::make()`, `Table::build()` and the `make:inertia-table` command;
- table resource schema v2, normalized URL state, selection descriptors,
  action requests, Saved View state and export requests;
- symbols and types exported by `resources/js/index.ts`, including component
  props, events, slots and composable return values;
- documented `tb-*` classes and CSS custom properties.

A patch release may fix behavior without changing these contracts. A minor
release may add optional resource fields, optional method parameters, new enum
cases, new exports or new capabilities. Existing consumers must continue to
compile and existing valid requests must retain their meaning.

Removing or renaming a public symbol, changing a required parameter, changing
the meaning or type of an existing serialized field, or incrementing the table
resource `schemaVersion` requires a major release. Deprecated APIs remain for at
least one minor release and are listed in the changelog and upgrade guide before
removal.

## Resource and persistence versions

The root `schemaVersion: 2` versions the PHP-to-frontend table resource. PHP and
Vue packages must use the same resource major version. Additive fields are
optional to custom renderers during a minor-release transition, then become part
of the next documented schema.

Saved View `state.schemaVersion`, queued-export snapshots and database migration
versions are independent persistence contracts. A root resource version change
does not invalidate stored views. Readers normalize older persisted state and
ignore undeclared columns, filters and capabilities.

## Extension boundaries

Private methods, internal support classes, generated `dist` layout, utility
class order, SQL implementation details and files below
`resources/js/components/ui` are not public extension points. Applications
should use documented table methods, exported composables/components, slots,
icon resolvers, `tb-*` classes, data attributes and CSS variables instead of
copying internals.

Generated table classes are application code. Regenerating with `--force` may
replace local edits, so upgrades never run the generator automatically.
