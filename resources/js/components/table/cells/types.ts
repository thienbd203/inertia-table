import type { TableColumn, TableItem } from "@/types";

export type CellPresentationProps = {
    item: TableItem;
    column: TableColumn;
};

export type CellImage = {
    urls?: string[];
    overflow?: number;
    icon?: string | null;
    size?: string;
    position?: "start" | "end";
    rounded?: boolean;
    width?: number | null;
    height?: number | null;
    class?: string | null;
    alt?: string | null;
    title?: string | null;
};

export function normalizeCellImage(value: unknown): CellImage | null {
    return value && typeof value === "object" ? (value as CellImage) : null;
}
