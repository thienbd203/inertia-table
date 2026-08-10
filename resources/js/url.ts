import type { TableItem, TableResource, TableState } from "./types";

const BASE_URL = "http://toolbelt.local";

function stateKey(table: string, key: string): string {
    return `table[${table}][${key}]`;
}

function filterKey(table: string, filter: string): string {
    return `table[${table}][filters][${filter}]`;
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

    if (state.search !== "") {
        params.set(stateKey(table, "search"), state.search);
    }

    if (state.sort) {
        params.set(stateKey(table, "sort"), state.sort);
    }

    if (state.page > 1) {
        params.set(stateKey(table, "page"), String(state.page));
    }

    params.set(stateKey(table, "perPage"), String(state.perPage));

    for (const [attribute, value] of Object.entries(state.filters)) {
        if (value !== null && value !== undefined && value !== "") {
            params.set(filterKey(table, attribute), String(value));
        }
    }

    return `${url.pathname}${url.search}${url.hash}`;
}
