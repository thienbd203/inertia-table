import {
    computed,
    nextTick,
    onMounted,
    onScopeDispose,
    reactive,
    type ComponentPublicInstance,
} from "vue";
import type { TableItem } from "./types";
import type { UseTable } from "./useTable";

export type StickySide = "left" | "right";

const SELECTION_COLUMN = "__selection";

export function useStickyColumns<T extends TableItem>(table: UseTable<T>) {
    const widths = reactive(new Map<string, number>());
    const elements = new Map<string, HTMLElement>();
    const elementKeys = new Map<HTMLElement, string>();
    const observer =
        typeof ResizeObserver === "undefined"
            ? null
            : new ResizeObserver((entries) => {
                  for (const entry of entries) {
                      const element = entry.target as HTMLElement;
                      const key = elementKeys.get(element);
                      if (key) measure(key, element);
                  }
              });

    const pinnedColumns = computed(() => {
        const visible = table.visibleColumns.value;
        const configured = table.state.value.pinnedColumns;
        const fallback = { left: [] as string[], right: [] as string[] };

        if (configured === undefined) {
            const lastIndex = Math.max(visible.length - 1, 0);
            visible.forEach((column, index) => {
                if (!column.sticky) return;
                fallback[index <= lastIndex / 2 ? "left" : "right"].push(
                    column.attribute,
                );
            });
        }

        const left = new Set(configured?.left ?? fallback.left);
        const right = new Set(configured?.right ?? fallback.right);

        return {
            left: visible
                .filter((column) => left.has(column.attribute))
                .map((column) => column.attribute),
            right: visible
                .filter(
                    (column) =>
                        right.has(column.attribute) &&
                        !left.has(column.attribute),
                )
                .map((column) => column.attribute),
        };
    });

    const selectionPinned = computed(() => pinnedColumns.value.left.length > 0);

    function resolveElement(
        value: Element | ComponentPublicInstance | null,
    ): HTMLElement | null {
        if (value instanceof HTMLElement) return value;
        if (value instanceof Element || value === null) return null;

        const root = value?.$el;

        return root instanceof HTMLElement ? root : null;
    }

    function measure(key: string, element: HTMLElement) {
        const width =
            element.getBoundingClientRect().width || element.offsetWidth;

        if (widths.get(key) !== width) widths.set(key, width);
    }

    function registerHeaderCell(
        key: string,
        value: Element | ComponentPublicInstance | null,
    ) {
        const previous = elements.get(key);
        const element = resolveElement(value);

        if (previous === element) return;

        if (previous) {
            observer?.unobserve(previous);
            elementKeys.delete(previous);
            elements.delete(key);
            widths.delete(key);
        }

        if (!element) return;

        elements.set(key, element);
        elementKeys.set(element, key);
        observer?.observe(element);
        void nextTick(() => measure(key, element));
    }

    function pinSide(attribute: string): StickySide | null {
        if (pinnedColumns.value.left.includes(attribute)) return "left";
        if (pinnedColumns.value.right.includes(attribute)) return "right";

        return null;
    }

    function offset(attribute: string, side: StickySide): number {
        const pinned = pinnedColumns.value[side];
        const index = pinned.indexOf(attribute);
        if (index < 0) return 0;

        if (side === "left") {
            return (
                (selectionPinned.value
                    ? (widths.get(SELECTION_COLUMN) ?? 0)
                    : 0) +
                pinned
                    .slice(0, index)
                    .reduce(
                        (total, key) =>
                            total +
                            (table.columnWidth(key) ?? widths.get(key) ?? 0),
                        0,
                    )
            );
        }

        return pinned
            .slice(index + 1)
            .reduce(
                (total, key) =>
                    total + (table.columnWidth(key) ?? widths.get(key) ?? 0),
                0,
            );
    }

    function style(attribute: string): Record<string, string> | undefined {
        if (attribute === SELECTION_COLUMN && selectionPinned.value) {
            return { insetInlineStart: "0px" };
        }

        const side = pinSide(attribute);
        if (!side) return undefined;

        return side === "left"
            ? { insetInlineStart: `${offset(attribute, side)}px` }
            : { insetInlineEnd: `${offset(attribute, side)}px` };
    }

    function edge(attribute: string): "start" | "end" | null {
        const left = pinnedColumns.value.left;
        const right = pinnedColumns.value.right;

        if (attribute === left.at(-1)) return "start";
        if (attribute === right[0]) return "end";

        return null;
    }

    function measureAll() {
        elements.forEach((element, key) => measure(key, element));
    }

    onMounted(() => window.addEventListener("resize", measureAll));
    onScopeDispose(() => {
        observer?.disconnect();
        if (typeof window !== "undefined") {
            window.removeEventListener("resize", measureAll);
        }
    });

    return {
        edge,
        measureAll,
        pinSide,
        pinnedColumns,
        registerHeaderCell,
        selectionColumn: SELECTION_COLUMN,
        selectionPinned,
        style,
    };
}

export type UseStickyColumns<T extends TableItem = TableItem> = ReturnType<
    typeof useStickyColumns<T>
>;
