import type { TableItem, TableResource, TableState } from "./types";

const BASE_URL = "http://toolbelt.local";

function stateKey(table: string, key: string): string {
    return `table[${table}][${key}]`;
}

function nestedKey(
    table: string,
    group: string,
    key: string,
    leaf?: string,
): string {
    return `table[${table}][${group}][${key}]${leaf ? `[${leaf}]` : ""}`;
}

export function tableUrl<T extends TableItem>(
    currentUrl: string,
    resource: TableResource<T>,
    state: TableState,
): string {
    const url = new URL(currentUrl, BASE_URL);
    const params = url.searchParams;
    const table = resource.name;
    const prefix = `table[${table}]`;

    for (const key of [...params.keys()]) {
        if (key.startsWith(`${prefix}[`)) {
            params.delete(key);
        }
    }

    if (state.view !== undefined && state.view !== null) {
        params.set(stateKey(table, "view"), String(state.view));
    }

    if (state.search !== "" || state.view != null) {
        params.set(stateKey(table, "search"), state.search);
    }

    if (state.sort) {
        params.set(stateKey(table, "sort"), state.sort);
    } else if (state.view != null) {
        params.set(stateKey(table, "sort"), "");
    }

    if (state.page > 1) {
        params.set(stateKey(table, "page"), String(state.page));
    }

    params.set(stateKey(table, "perPage"), String(state.perPage));

    for (const [attribute, filter] of Object.entries(state.filters)) {
        if (!filter.enabled && state.view == null) continue;

        params.set(
            nestedKey(table, "filters", attribute, "enabled"),
            filter.enabled ? "1" : "0",
        );
        params.set(
            nestedKey(table, "filters", attribute, "clause"),
            filter.clause,
        );
        const valueKey = nestedKey(table, "filters", attribute, "value");

        if (filter.value === null || filter.value === undefined) {
            continue;
        }

        if (Array.isArray(filter.value)) {
            for (const value of filter.value) {
                params.append(`${valueKey}[]`, String(value));
            }
        } else {
            params.set(valueKey, String(filter.value));
        }
    }

    for (const [attribute, visible] of Object.entries(state.columns)) {
        params.set(nestedKey(table, "columns", attribute), visible ? "1" : "0");
    }

    return `${url.pathname}${url.search}${url.hash}`;
}
