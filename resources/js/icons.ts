import type { Component } from "vue";
import type { TableAction } from "./types";

export type IconResolver = (
    icon: string,
    context: TableAction,
) => Component | undefined | null;

let globalIconResolver: IconResolver | null = null;

export function setIconResolver(resolver: IconResolver | null): void {
    globalIconResolver = resolver;
}

export function resolveIcon(
    icon: string,
    context: TableAction,
    resolver?: IconResolver,
): Component | null {
    return (
        resolver?.(icon, context) ?? globalIconResolver?.(icon, context) ?? null
    );
}
