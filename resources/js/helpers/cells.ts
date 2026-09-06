import type { TableColumn, TableItem, TableUrl } from "@/types";

export function cellValue(item: TableItem, attribute: string): unknown {
    return (item as Record<string, unknown>)[attribute];
}

export function cellUrl(item: TableItem, attribute: string): TableUrl | null {
    const columns = item._table?.columns;

    return normalizeUrl(
        Array.isArray(columns) ? undefined : columns?.[attribute],
    );
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

export function displayValue(
    item: TableItem,
    column: TableColumn,
    booleanLabels: { trueLabel: string; falseLabel: string } = {
        trueLabel: "Yes",
        falseLabel: "No",
    },
): unknown {
    const value = cellValue(item, column.attribute);

    return column.type === "boolean"
        ? value
            ? (column.trueLabel ?? booleanLabels.trueLabel)
            : (column.falseLabel ?? booleanLabels.falseLabel)
        : value;
}

export function cellMeta(
    item: TableItem,
    attribute: string,
): Record<string, unknown> {
    const cells = item._table?.cells;

    return Array.isArray(cells) ? {} : (cells?.[attribute] ?? {});
}
