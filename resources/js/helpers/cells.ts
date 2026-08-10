import type { TableColumn, TableItem } from "@/types";

export function cellValue(item: TableItem, attribute: string): unknown {
    return (item as Record<string, unknown>)[attribute];
}

export function cellUrl(item: TableItem, attribute: string): string | null {
    return item._table?.columns[attribute] ?? null;
}

export function rowUrl(item: TableItem): string | null {
    return item._table?.url ?? null;
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
