# Styling

The renderer uses Tailwind utility classes and application theme variables. Use
documented `tb-*` classes and CSS custom properties for stable overrides rather
than copying internal UI components.

## Tailwind source

```css
@source '../../node_modules/@musing/inertia-table-vue/resources/js/**/*.vue';
```

Import the renderer stylesheet once:

```ts
import "@musing/inertia-table-vue/style.css";
```

## Stable hooks

Common layout hooks include:

- `tb-wrapper`
- `tb-active-filter`
- `tb-filter-editor`
- `tb-summary-footer`, `tb-summary-row`, `tb-summary-value`
- `tb-resizable-column`, `tb-reorderable-column`
- `tb-column-resize-handle`, `tb-column-reorder-handle`
- `tb-column-header-content`
- `tb-sticky-footer-cell`, `tb-sticky-footer-container`

Tailwind utility order and components below `resources/js/components/ui` are
internal implementation details.

## Column layout variables

```css
.products-table {
    --tb-resize-handle-hit-area: 14px;
    --tb-resize-handle-width: 2px;
    --tb-resize-handle-color: theme(colors.teal.500);
    --tb-reorder-handle-size: 1rem;
    --tb-column-header-gap: 0.375rem;
    --tb-column-drop-color: theme(colors.blue.500);
}
```

Header interaction colors:

- `--tb-header-hover-background`
- `--tb-header-button-hover-background`
- `--tb-header-hover-foreground`
- `--tb-header-active-background`
- `--tb-header-active-foreground`

## Sticky layout variables

```css
.products-table {
    --tb-sticky-max-height: 75vh;
    --tb-sticky-backdrop-filter: blur(6px);
}
```

Sticky headers always use an opaque background. The optional backdrop filter
applies to horizontally sticky body and footer cells. Disable it globally or per
table when repainting becomes expensive.

## Summary variables

- `--tb-summary-background`
- `--tb-summary-border-color`
- `--tb-summary-font-weight`

Pinned summary cells retain the same edge and backdrop hooks as body cells.

## Safe row styling

Return scalar `data-*` values from `dataAttributesForModel()` and target them in
CSS:

```css
.products-table tr[data-status="archived"] {
    opacity: 0.72;
}
```

Do not attempt to replace package-owned selection or navigation attributes.
