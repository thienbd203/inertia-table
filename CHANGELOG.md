# Changelog

All notable changes to `inertia-table` will be documented in this file.

## Unreleased

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
