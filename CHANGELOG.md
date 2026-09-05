# Changelog

All notable changes to `inertia-table` will be documented in this file.

## Unreleased

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
- Signed, server-driven `SetFilter` option sources with debounced search, opaque
  cursor pagination, dependency allowlists and independent authorization.
- Optional facet counts derived from the normalized table query, selected-label
  hydration, bounded client caching and loading/error/retry option states.

### Changed

- Expanded release validation across supported PHP, Laravel, Inertia, Node and
  database versions, with dependency audits before npm publishing.

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
