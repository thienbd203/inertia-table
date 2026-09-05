# Customization and translations

The server owns query capabilities and normalized metadata. The Vue package
owns interaction and default rendering. Application-specific wording, icons and
presentation can be replaced without forking either layer.

## Translations

Laravel translates server-generated defaults such as action confirmation text,
boolean labels, the action-column heading and the default genuine empty-state
title. Publish and edit those files with:

```bash
php artisan vendor:publish --tag=inertia-table-translations
```

Application-owned column, filter-option, action and empty-state labels should be
translated before they are passed to the package.

The Vue renderer ships complete English and Vietnamese catalogs. Configure an
application-wide locale and partial overrides through the plugin:

```ts
import { createInertiaTable, vi } from "@musing/inertia-table-vue";

app.use(createInertiaTable({
    locale: "vi-VN",
    messages: {
        ...vi,
        searchPlaceholder: "Tìm chủ đề…",
    },
}));
```

`DataTable` accepts the same `locale` and `messages` props for a local override.
Local messages fall back to the application catalog and then English. Message
parameters use `{name}` syntax; missing parameters stay visible to expose an
incomplete translation during development.

## Icons

Built-in Lucide names resolve automatically. Use `iconResolver` on one table or
`setIconResolver()` for application-wide aliases and custom components. The
resolver receives both the icon name and its context, such as a column, action,
empty state or empty-state action.

## Rendering and styles

Use attribute slots such as `cell(name)`, `header(name)`, `filter(status)` and
`image(avatar)` for targeted changes. Layout slots include `topbar`,
`beforeSearch`, `afterSearch`, `beforeActions`, `afterActions`, `filters`,
`table`, `thead`, `tbody`, `summaryFooter`, `footer`, `loading`, `emptyState` and
`confirmation`. Use `summary(attribute)` for one summary cell.

Prefer documented `tb-*` classes and CSS custom properties for styling.
Tailwind utility order and internal shadcn primitives may change in minor
releases. Server row attributes belong in
`dataAttributesForModel()`; names are normalized to `data-*`, scalar-only and
cannot replace package-owned selection or navigation attributes.

Column layout exposes `tb-resizable-column`, `tb-reorderable-column`,
`tb-column-resize-handle`, `tb-column-reorder-handle` and
`tb-column-header-content`. Resize/reorder affordances can be tuned with
`--tb-resize-handle-hit-area`, `--tb-resize-handle-width`,
`--tb-resize-handle-inset`, `--tb-resize-handle-color`,
`--tb-reorder-handle-size`, `--tb-column-header-gap` and
`--tb-column-drop-color`. Header interactions use three visual layers: the
whole header row, the active button, then the resize guide. They can be tuned
with `--tb-header-hover-background`, `--tb-header-button-hover-background`,
`--tb-header-hover-foreground`, `--tb-header-active-background`, and
`--tb-header-active-foreground`.

Sticky headers always use an opaque background; the optional backdrop filter
applies only to horizontally sticky body and summary-footer cells.

Summary footers expose `tb-summary-footer`, `tb-summary-row`, and
`tb-summary-value`. Their surface can be adjusted with
`--tb-summary-background`, `--tb-summary-border-color`, and
`--tb-summary-font-weight`; horizontally sticky summary cells retain the same
sticky edge and backdrop hooks as headers and body cells. When `stickyFooter`
is enabled, footer cells also expose `tb-sticky-footer-cell` and the scroll
container exposes `tb-sticky-footer-container`.

For a fully application-owned renderer, compose `useTable()`, `useActions()`,
`useViews()` and `useExports()` against the typed `TableResource`. Keep the
server resource authoritative instead of deriving searchable, selectable or
actionable behavior from visible DOM rows.
