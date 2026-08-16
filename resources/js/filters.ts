import type { TableFilter, TableFilterState } from "./types";

const clauseSymbols: Record<string, string> = {
    after: ">",
    before: "<",
    between: "↔",
    contains: "*",
    ends_with: "$",
    equal_or_after: ">=",
    equal_or_before: "<=",
    equals: "=",
    greater_than: ">",
    greater_than_or_equal: ">=",
    in: "∈",
    is_false: "= false",
    is_not_set: "∅",
    is_set: "≠ ∅",
    is_true: "= true",
    less_than: "<",
    less_than_or_equal: "<=",
    not_between: "↮",
    not_contains: "!*",
    not_ends_with: "!$",
    not_equals: "!=",
    not_in: "∉",
    not_starts_with: "!^",
    starts_with: "^",
};

export function setClauseSymbols(symbols: Record<string, string>) {
    Object.assign(clauseSymbols, symbols);
}

export function clauseSymbol(clause: string): string {
    return clauseSymbols[clause] ?? clause.replaceAll("_", " ");
}

export function filterDisplayValue(
    filter: TableFilter,
    state: TableFilterState | undefined,
): string {
    if (!state) return "";

    if (
        ["is_true", "is_false", "is_set", "is_not_set"].includes(state.clause)
    ) {
        return "";
    }

    const values = Array.isArray(state.value) ? state.value : [state.value];

    if (filter.compactDisplayLabel && values.length > 1) {
        return `${values.length} ${filter.compactDisplayLabel}`;
    }

    return filterFullDisplayValue(filter, state);
}

export function filterFullDisplayValue(
    filter: TableFilter,
    state: TableFilterState | undefined,
): string {
    if (!state) return "";

    const values = Array.isArray(state.value) ? state.value : [state.value];

    return values
        .map((value) => {
            const option = filter.options.find(
                (candidate) => String(candidate.value) === String(value),
            );

            return option?.label ?? String(value ?? "");
        })
        .filter(Boolean)
        .join(", ");
}
