import type { TableColumn, TableItem, TableUrl } from "@/types";

export function cellValue(item: TableItem, attribute: string): unknown {
    return (item as Record<string, unknown>)[attribute];
}

export function cellUrl(item: TableItem, attribute: string): TableUrl | null {
    return normalizeUrl(item._table?.columns[attribute]);
}

export function rowUrl(item: TableItem): TableUrl | null {
    return normalizeUrl(item._table?.url);
}

function normalizeUrl(
    value: TableUrl | string | null | undefined,
): TableUrl | null {
    if (typeof value === "string") {
        return {
            url: value,
            preserveScroll: true,
            preserveState: true,
            newTab: false,
            download: false,
            disabled: false,
        };
    }

    return value ?? null;
}

export function displayValue(item: TableItem, column: TableColumn): unknown {
    const value = cellValue(item, column.attribute);

    return column.type === "boolean"
        ? value
            ? (column.trueLabel ?? "Yes")
            : (column.falseLabel ?? "No")
        : value;
}

export function cellMeta(
    item: TableItem,
    attribute: string,
): Record<string, unknown> {
    return item._table?.cells?.[attribute] ?? {};
}
