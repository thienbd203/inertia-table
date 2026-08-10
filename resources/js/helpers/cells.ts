import type { TableColumn, TableItem } from "../types";

export function cellValue(item: TableItem, attribute: string): unknown {
    return (item as Record<string, unknown>)[attribute];
}

export function cellUrl(item: TableItem, attribute: string): string | null {
    return item._table?.columns[attribute] ?? item._table?.url ?? null;
}

export function displayValue(item: TableItem, column: TableColumn): unknown {
    const value = cellValue(item, column.attribute);

    return column.type === "boolean" ? (value ? "Yes" : "No") : value;
}
