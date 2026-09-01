import { router, usePage } from "@inertiajs/vue3";
import {
    computed,
    onScopeDispose,
    ref,
    toValue,
    watch,
    type MaybeRefOrGetter,
} from "vue";
import type { TableItem, TableResource, TableState } from "./types";
import { tableUrl } from "./url";

export function useTable<T extends TableItem>(
    resource: MaybeRefOrGetter<TableResource<T>>,
) {
    const page = usePage();
    const search = ref(toValue(resource).state.search);
    const isNavigating = ref(false);
    let debounceTimer: ReturnType<typeof setTimeout> | undefined;
    let latestVisit = 0;

    watch(
        () => toValue(resource).state.search,
        (value, previousValue) => {
            // The server may normalize the query (e.g. trim whitespace). Keep
            // the input's draft untouched unless it has not been edited since
            // the previous state was received.
            if (search.value === previousValue) {
                search.value = value;
            }
        },
    );

    function visit(state: TableState, replace = true) {
        const current = toValue(resource);
        const visitId = ++latestVisit;
        isNavigating.value = true;

        try {
            router.visit(tableUrl(page.url, current, state), {
                method: "get",
                preserveScroll: true,
                preserveState: true,
                replace,
                only: [current.name, ...current.options.reloadProps],
                onFinish: () => {
                    if (visitId === latestVisit) {
                        isNavigating.value = false;
                    }
                },
            });
        } catch (error) {
            if (visitId === latestVisit) {
                isNavigating.value = false;
            }

            throw error;
        }
    }

    function patchState(patch: Partial<TableState>) {
        visit({ ...toValue(resource).state, ...patch });
    }

    function setSearch(value: string) {
        if (!toValue(resource).capabilities.searchable) return;

        search.value = value;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            patchState({ search: value.trim(), page: 1, cursor: null });
        }, toValue(resource).options.debounceTime);
    }

    function setSort(attribute: string, direction?: "asc" | "desc") {
        const current = toValue(resource);
        const column = current.columns.find(
            (candidate) => candidate.attribute === attribute,
        );

        if (!column?.sortable) return;

        patchState({
            sort:
                direction === "desc"
                    ? `-${attribute}`
                    : direction === "asc"
                      ? attribute
                      : current.state.sort === attribute
                        ? `-${attribute}`
                        : attribute,
            page: 1,
            cursor: null,
        });
    }

    function setFilter(attribute: string, value: unknown, clause?: string) {
        const current = toValue(resource);
        const definition = current.filters.find(
            (filter) => filter.attribute === attribute,
        );

        if (!definition) return;

        const filters = { ...current.state.filters };

        if (value === null || value === undefined || value === "") {
            filters[attribute] = {
                enabled: false,
                clause: clause ?? definition.clauses[0] ?? "equals",
                value: null,
            };
        } else {
            const selectedClause = clause ?? definition.clauses[0];
            if (!selectedClause || !definition.clauses.includes(selectedClause))
                return;

            filters[attribute] = {
                enabled: true,
                clause: selectedClause,
                value,
            };
        }

        patchState({ filters, page: 1, cursor: null });
    }

    function removeFilter(attribute: string) {
        const filters = { ...toValue(resource).state.filters };
        const definition = toValue(resource).filters.find(
            (filter) => filter.attribute === attribute,
        );
        if (!definition) return;
        filters[attribute] = {
            enabled: false,
            clause: definition.clauses[0] ?? "equals",
            value: null,
        };
        patchState({ filters, page: 1, cursor: null });
    }

    function clearFilters() {
        const filters = Object.fromEntries(
            toValue(resource).filters.map((filter) => [
                filter.attribute,
                {
                    enabled: false,
                    clause: filter.clauses[0] ?? "equals",
                    value: null,
                },
            ]),
        );
        patchState({ filters, page: 1, cursor: null });
    }

    function clearAll() {
        search.value = "";
        clearTimeout(debounceTimer);
        const filters = Object.fromEntries(
            toValue(resource).filters.map((filter) => [
                filter.attribute,
                {
                    enabled: false,
                    clause: filter.clauses[0] ?? "equals",
                    value: null,
                },
            ]),
        );
        patchState({ search: "", filters, page: 1, cursor: null });
    }

    function toggleColumn(attribute: string) {
        const current = toValue(resource);
        const definition = current.columns.find(
            (column) => column.attribute === attribute,
        );

        if (!definition?.toggleable) return;

        patchState({
            columns: {
                ...current.state.columns,
                [attribute]: !current.state.columns[attribute],
            },
        });
    }

    function columnPinSide(attribute: string): "left" | "right" | null {
        const pinned = toValue(resource).state.pinnedColumns;

        if (pinned?.left.includes(attribute)) return "left";
        if (pinned?.right.includes(attribute)) return "right";

        return null;
    }

    function togglePinnedColumn(attribute: string) {
        const current = toValue(resource);
        const definition = current.columns.find(
            (column) => column.attribute === attribute,
        );

        if (!definition?.stickable || definition.sticky) return;

        const visible = current.columns.filter(
            (column) => current.state.columns[column.attribute] !== false,
        );
        const index = visible.findIndex(
            (column) => column.attribute === attribute,
        );
        if (index < 0) return;

        const existing = current.state.pinnedColumns ?? {
            left: [],
            right: [],
        };
        const currentSide = columnPinSide(attribute);
        const side =
            currentSide ??
            (index <= (visible.length - 1) / 2 ? "left" : "right");
        const permanent = new Set(
            current.columns
                .filter((column) => column.sticky)
                .map((column) => column.attribute),
        );
        const positions = new Map(
            visible.map((column, position) => [column.attribute, position]),
        );
        const next = {
            left: [...existing.left],
            right: [...existing.right],
        };

        if (currentSide) {
            next[side] = next[side].filter((column) => {
                if (permanent.has(column)) return true;

                const position = positions.get(column);
                if (position === undefined) return true;

                return side === "left" ? position < index : position > index;
            });
        } else {
            const edgeColumns = (
                side === "left"
                    ? visible.slice(0, index + 1)
                    : visible.slice(index)
            )
                .filter((column) => column.stickable)
                .map((column) => column.attribute);

            next[side] = [...new Set([...next[side], ...edgeColumns])];
            const opposite = side === "left" ? "right" : "left";
            next[opposite] = next[opposite].filter(
                (column) => !edgeColumns.includes(column),
            );
        }

        for (const candidate of ["left", "right"] as const) {
            const selected = new Set(next[candidate]);
            next[candidate] = current.columns
                .filter((column) => selected.has(column.attribute))
                .map((column) => column.attribute);
        }

        patchState({ pinnedColumns: next });
    }

    function setPage(page: number) {
        const current = toValue(resource);
        if (!current.capabilities.paginated) return;
        if (current.options.paginationType === "cursor") return;
        if (page < 1) return;
        if (
            current.results.lastPage !== null &&
            page > current.results.lastPage
        )
            return;
        patchState({ page });
    }

    function setCursor(cursor: string | null) {
        const current = toValue(resource);
        if (!current.capabilities.paginated) return;
        if (current.options.paginationType !== "cursor") return;
        if (!cursor) return;
        patchState({ cursor, page: 1 });
    }

    function setPerPage(perPage: number) {
        if (!toValue(resource).capabilities.paginated) return;
        if (!toValue(resource).options.perPage.includes(perPage)) return;
        patchState({ perPage, page: 1, cursor: null });
    }

    const state = computed(() => toValue(resource).state);
    const visibleColumns = computed(() =>
        toValue(resource).columns.filter(
            (column) =>
                toValue(resource).state.columns[column.attribute] !== false,
        ),
    );
    const hasFilters = computed(() =>
        Object.values(toValue(resource).state.filters).some(
            (filter) => filter.enabled,
        ),
    );
    const hasActiveState = computed(
        () => toValue(resource).state.search !== "" || hasFilters.value,
    );

    onScopeDispose(() => {
        clearTimeout(debounceTimer);
        latestVisit++;
        isNavigating.value = false;
    });

    return {
        clearAll,
        clearFilters,
        columnPinSide,
        hasActiveState,
        hasFilters,
        isNavigating,
        patchState,
        removeFilter,
        resource: computed(() => toValue(resource)),
        search,
        setFilter,
        setCursor,
        setPage,
        setPerPage,
        setSearch,
        setSort,
        state,
        toggleColumn,
        togglePinnedColumn,
        visibleColumns,
        visit,
    };
}

export type UseTable<T extends TableItem = TableItem> = ReturnType<
    typeof useTable<T>
>;
