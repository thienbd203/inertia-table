# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Toolbelt Inertia Table is a two-package monorepo: a Laravel/PHP package (`toolbelt/inertia-table`, root namespace `Toolbelt\InertiaTable`) that defines server-driven data tables, and a Vue renderer (`@toolbelt/inertia-table-vue`) that consumes the serialized table resource and renders it with Inertia.js. The PHP side is authoritative — the frontend can only request search/sort/filter/action operations the server has explicitly declared and allowlisted (via Spatie Laravel Query Builder). See [docs/architecture.md](docs/architecture.md) for the full design contract (resource shape, URL contract, layering rules) before making cross-cutting changes.

The package is pre-`v1.0` and the resource/API contract is still versioned as a spike (`schemaVersion: 1`).

## Commands

### PHP (root package)

```bash
composer install
composer test              # vendor/bin/pest
composer test-coverage     # vendor/bin/pest --coverage
composer analyse           # vendor/bin/phpstan analyse (level 5, src + config)
composer format             # vendor/bin/pint
```

Run a single Pest test: `vendor/bin/pest tests/TableTest.php` or filter by name with `vendor/bin/pest --filter="name of test"`.

### JavaScript/Vue (`resources/js`, built via Vite)

```bash
npm install
npm test                   # vitest run
npm run types:check        # vue-tsc --noEmit
npm run format:check       # prettier --check
npm run format             # prettier --write
npm run build               # vite build -> dist/
```

Run a single Vitest file: `npx vitest run tests-js/useTable.test.ts`.

CI (`.github/workflows/run-tests.yml`, `run-js-tests.yml`) runs PHP tests only against PHP 8.4 / Laravel 13 / Testbench 11, and JS tests separately — mirror that when validating changes.

## Architecture

### PHP core (`src/`)

- **`Table`** (`src/Table.php`) is the abstract base every table definition extends. It wires together `columns()`, `filters()`, `actions()`, and `query()` into a `TableResource`. Key responsibilities live in `resolve()`: parse request state via `TableState::fromRequest()`, normalize sort/filters/columns against what's actually declared (dropping anything invalid), build a Spatie `QueryBuilder` with allowlisted filters/sorts, paginate, and serialize each row via `serializeRow()`.
- **`Columns\Column`** is the base for all column types (`TextColumn`, `NumberColumn`, `NumericColumn`, `BadgeColumn`, `BooleanColumn`, `DateColumn`, `DateTimeColumn`, `ImageColumn`, `ActionColumn`). Columns own value resolution (`resolveValue`), per-cell URLs (`resolveUrl`), sorting (`applySort`/`allowedSort`), and search (`applySearch`). Sorting can be a plain `orderBy`, a custom `sortUsing()` closure, or a `sortUsingMap()` (compiles to a SQL `CASE` expression) — the `Table` never uses raw client-supplied sort strings directly against the query.
- **`Filters\Filter`** is the base for filter types (`TextFilter`, `NumericFilter`, `SetFilter`/deprecated `SelectFilter`, `BooleanFilter`, `DateFilter`). Each filter declares its own allowed `Clause`s (enum in `src/Filters/Clause.php`), normalizes incoming state (`normalizeState`), and implements `apply()` to translate a clause+value into query constraints. `allowedFilter()` wraps this as a Spatie `AllowedFilter::callback`. Filters that don't recognize their own clause silently no-op rather than falling through to raw input.
- **`Actions\Action`** declares row/bulk/both-scoped actions with authorization (`authorized()`), visibility, disabled state, confirmation dialogs, and an `endpoint()` (HTTP method + URL) or no endpoint at all (frontend-owned via the `custom-action` Vue event).
- **`TableState`** is the canonical, validated representation of one table's URL state (search, sort, filters, columns, page, perPage) parsed from the namespaced `table[<name>][...]` query params. **`TableResource`** is the final `Arrayable` DTO serialized to the Inertia prop (`schemaVersion`, `columns`, `filters`, `actions`, `search`, `capabilities`, `state`, `results`, `options`).
- Every table gets an isolated query-string namespace (`table[<name>][search|sort|filters|columns|page|perPage]`), so multiple table resources can coexist on one Inertia page. `Table::queryBuilderRequest()` builds an internal, isolated `Request` for Spatie so the app's global query string is never mutated.

### Vue renderer (`resources/js`)

- **`useTable(resource)`** (`resources/js/useTable.ts`) is the headless composable owning resolved state and navigation: search (debounced via `options.debounceTime`), sort, filters, column visibility, pagination. All mutations funnel through `patchState()` → `tableUrl()` → `router.visit()` with `only: [name, ...reloadProps]` (a scoped Inertia partial reload).
- **`useActions(table, options, callbacks)`** (`resources/js/useActions.ts`) owns row/bulk selection (keyed via `rowKey`, defaulting to `item.id`), action execution (`router.visit` for endpoint-backed actions, or the `onCustomAction` callback for frontend-owned actions), and confirmation flow. Selection is intentionally not part of URL state — it's cleared whenever the result set's row keys change.
- **`url.ts`** (`tableUrl()`) is the single place that serializes `TableState` back into the namespaced query string; it strips only that table's existing `table[<name>][...]` params before rewriting, preserving unrelated query params and other tables' namespaces untouched.
- **`DataTable.vue`** is the default renderer composed from `components/table/*` (actions, cells, columns, filters, layout, rows) and `components/ui/*` (vendored shadcn-vue-style primitives — Button, Checkbox, Dialog, DropdownMenu, Input, NativeSelect, Popover, Table). `components/ui` must stay a faithful vendor of shadcn-vue source (no parallel reimplementation); table-specific composition belongs in `components/table`.
- Icons are resolved through an injectable resolver (`icons.ts` / `setIconResolver`) rather than a hard dependency on a specific icon set — hosts register their own Lucide icon map.
- The frontend core (`useTable`, `useActions`, `url.ts`, `types.ts`) must stay free of Vue-DOM/shadcn-specific imports so it could be extracted into a framework-neutral package later, per the architecture doc's layering rule.

### Contract discipline

Because the frontend can only act on capabilities the server declares, most bugs/features touch **both** a PHP resource field and its corresponding TS type in `resources/js/types.ts` plus consuming logic in `useTable.ts`/`useActions.ts`. When adding a new column/filter/action capability, update: the PHP class's `toArray()`, the TS type, any renderer component that reads it, and the relevant Pest contract test plus Vitest test.

## Testing conventions

- PHP tests use Pest (`tests/`), with `TestCase` (`tests/TestCase.php`) booting an Orchestra Testbench app with an in-memory SQLite connection. `ArchTest.php` enforces no `dd`/`dump`/`ray` calls in the codebase.
- JS tests use Vitest with `happy-dom` (`tests-js/`), including composable tests (`useTable.test.ts`, `useActions.test.ts`), URL serialization (`url.test.ts`), and component tests (`DataTable.test.ts`) via `@vue/test-utils`. `fixtures.ts` holds shared `TableResource` test fixtures.
