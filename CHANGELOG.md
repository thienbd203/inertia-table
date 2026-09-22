# Changelog

All notable changes to `inertia-table` will be documented in this file.

## 1.0.0 - 2026-09-21

First stable release of the Laravel package and Vue renderer. The documented
public APIs follow semantic versioning; the table resource remains schema v2.
See [API stability](docs/internals/api-stability.md) for compatibility guarantees.

### Added

- Queued managed bulk actions with immutable selection snapshots, idempotent
  dispatch, actor/tenant restoration, chunk progress and signed status polling.
- Vue queued-action lifecycle events, scoped polling and a dismissible progress
  dialog that reloads only declared Inertia props after completion.
- Server-normalized column widths and order with pointer, touch and keyboard
  controls, sticky-offset integration and per-table/global feature gates.
- Saved View persistence for column layout and opt-in exports that follow the
  visible user column order.
- Server-side `count`, `count_distinct`, `sum`, `avg`, `min`, `max`, and custom
  summaries for the complete filtered dataset, combined into one built-in
  aggregate query.
- A sticky-aware summary footer with per-cell/whole-footer slots, loading state,
  locale-aware formatting, and opt-in native CSV summary rows.
- Lazy `SetFilter` options loaded when the editor is opened, with immediate
  filter application and no Apply button. Remote filter sources are not supported.
- Documentation site, runnable consumer examples, and PHP/Vue contract checks.
- Filter resources now expose optional `clauseValueKinds` metadata so custom
  clauses can declare whether they accept one value, a range, or no value.

### Changed

- Queued exports now retain the dispatch locale through generation and lifecycle
  callbacks, expose a safe public failure message, and preserve a terminal
  failed status when preparation fails after idempotency reservation.
- Expanded release validation across supported PHP, Laravel, Inertia, Node and
  database versions, with dependency audits before npm publishing.

### Fixed

- Mapped column sorts now keep values that are outside the map after mapped
  values in both ascending and descending order.
- Filter keyboard activation, focus restoration, accessible labels and header
  sort announcements, while retaining the existing filter-chip appearance.

### Known limitations

- Lazy filter chips can display an option ID after reload instead of its label.
- Resetting or switching Saved Views can retain empty draft filter chips.
- The save-view dialog emits a missing-description accessibility warning.
- Manual browser checks have not yet verified the complete export download,
  queued-worker completion, responsive/theme matrix or screen-reader journeys.
  Automated export coverage does not replace those manual checks.

## 0.7.0 - 2026-09-01

### Added

- Managed row and bulk action handlers with typed, server-authoritative selections.
- Exact selectable-row counts, per-row eligibility, three-state select-all and Shift-click ranges.
- Scoped Saved Views with signed CRUD endpoints and optimistic locking.
- Synchronous and queued exports for full, filtered and selected datasets.
- Allowlisted relationship search, filtering and sorting with shared query customization.
- Sticky headers plus user-toggleable and permanent sticky columns, measured stacked offsets, RTL support and Saved View persistence.
- Table resource schema v2 with genuine empty states, empty-state actions and safe per-row data attributes.
- Anonymous tables through `Table::build()`, optional unpaginated resources and the `make:inertia-table` generator.
- Full, simple and cursor pagination strategies with deterministic keyset ordering.
- Independent sticky-column pinning with configurable backdrop filtering.

### Changed

- Split cell presentation into focused renderers for values, badges and images.
- Added numbered controls to full pagination and a responsive two-row mobile footer.
- Updated table borders, clipping, sticky backgrounds and action-column header styling.
