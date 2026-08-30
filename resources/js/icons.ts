import type { Component } from "vue";
import type {
    TableAction,
    TableColumn,
    TableEmptyState,
    TableEmptyStateAction,
    TableItem,
} from "./types";

export type IconContext =
    | TableAction
    | TableEmptyState
    | TableEmptyStateAction
    | { column: TableColumn; item: TableItem; value: unknown };

export type IconResolver = (
    icon: string,
    context: IconContext,
) => Component | undefined | null;

let globalIconResolver: IconResolver | null = null;

export function setIconResolver(resolver: IconResolver | null): void {
    globalIconResolver = resolver;
}

export function resolveIcon(
    icon: string,
    context: IconContext,
    resolver?: IconResolver,
): Component | null {
    return (
        resolver?.(icon, context) ?? globalIconResolver?.(icon, context) ?? null
    );
}
