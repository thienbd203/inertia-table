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

const MAX_COLUMN_WIDTH = 10_000;

export function useTable<T extends TableItem>(
    resource: MaybeRefOrGetter<TableResource<T>>,
) {
    const page = usePage();
    const search = ref(toValue(resource).state.search);
    const isNavigating = ref(false);
    const resizingColumn = ref<string | null>(null);
    const columnOrder = ref(
        normalizeColumnOrder(
            toValue(resource).state.columnOrder,
            toValue(resource),
        ),
    );
    const columnWidths = ref<Record<string, number>>({
        ...(toValue(resource).state.columnWidths ?? {}),
    });
    let debounceTimer: ReturnType<typeof setTimeout> | undefined;
    let layoutTimer: ReturnType<typeof setTimeout> | undefined;
    let latestVisit = 0;

    function normalizeColumnOrder(
        requested: string[] | undefined,
        current: TableResource<T>,
    ): string[] {
        const declared = current.columns.map((column) => column.attribute);
        const known = new Set(declared);
        const normalized = [
            ...new Set((requested ?? []).filter((key) => known.has(key))),
        ];

        return [
            ...normalized,
            ...declared.filter((key) => !normalized.includes(key)),
        ];
    }

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

    watch(
        () => ({
            order: toValue(resource).state.columnOrder,
            widths: toValue(resource).state.columnWidths,
            columns: toValue(resource).columns.map((column) => ({
                attribute: column.attribute,
                width: column.width,
            })),
        }),
        ({ order, widths }) => {
            const current = toValue(resource);
            columnOrder.value = normalizeColumnOrder(order, current);
            columnWidths.value = { ...(widths ?? {}) };
        },
        { deep: true },
    );

    function visit(state: TableState, replace = true) {
        const current = toValue(resource);
        const visitId = ++latestVisit;
        clearTimeout(layoutTimer);
        layoutTimer = undefined;
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
        visit({ ...state.value, ...patch });
    }

    function scheduleLayoutVisit() {
        clearTimeout(layoutTimer);
        layoutTimer = setTimeout(() => {
            visit(state.value);
        }, toValue(resource).options.debounceTime);
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
        const pinned = state.value.pinnedColumns;

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

        const visible = visibleColumns.value;
        const index = visible.findIndex(
            (column) => column.attribute === attribute,
        );
        if (index < 0) return;

        const existing = state.value.pinnedColumns ?? {
            left: [],
            right: [],
        };
        const currentSide = columnPinSide(attribute);
        const side =
            currentSide ??
            (index <= (visible.length - 1) / 2 ? "left" : "right");
        const next = {
            left: [...existing.left],
            right: [...existing.right],
        };

        if (currentSide) {
            next[side] = next[side].filter((column) => column !== attribute);
        } else {
            next[side] = [...new Set([...next[side], attribute])];
            const opposite = side === "left" ? "right" : "left";
            next[opposite] = next[opposite].filter(
                (column) => column !== attribute,
            );
        }

        for (const candidate of ["left", "right"] as const) {
            const selected = new Set(next[candidate]);
            next[candidate] = orderedColumns.value
                .filter((column) => selected.has(column.attribute))
                .map((column) => column.attribute);
        }

        patchState({ pinnedColumns: next });
    }

    function columnWidth(attribute: string): number | null {
        const definition = toValue(resource).columns.find(
            (column) => column.attribute === attribute,
        );

        return columnWidths.value[attribute] ?? definition?.width ?? null;
    }

    function columnStyle(
        attribute: string,
    ): Record<string, string> | undefined {
        const definition = toValue(resource).columns.find(
            (column) => column.attribute === attribute,
        );
        if (!definition) return undefined;

        const width = columnWidth(attribute);
        if (width !== null) {
            const pixels = `${width}px`;

            return {
                inlineSize: pixels,
                minInlineSize: pixels,
                maxInlineSize: pixels,
            };
        }

        const style: Record<string, string> = {};
        if (definition.minWidth) {
            style.minInlineSize = `${definition.minWidth}px`;
        }
        if (definition.maxWidth) {
            style.maxInlineSize = `${definition.maxWidth}px`;
        }

        return Object.keys(style).length > 0 ? style : undefined;
    }

    function setColumnWidth(attribute: string, requested: number) {
        const current = toValue(resource);
        const definition = current.columns.find(
            (column) => column.attribute === attribute,
        );
        if (
            current.options.columnResizing === false ||
            !definition?.resizable ||
            !Number.isFinite(requested)
        ) {
            return;
        }

        const minimum = definition.minWidth ?? 1;
        const maximum = Math.min(
            definition.maxWidth ?? MAX_COLUMN_WIDTH,
            MAX_COLUMN_WIDTH,
        );
        const width = Math.min(
            Math.max(Math.round(requested), minimum),
            maximum,
        );
        columnWidths.value = { ...columnWidths.value, [attribute]: width };
        scheduleLayoutVisit();
    }

    function setResizingColumn(attribute: string | null) {
        resizingColumn.value = attribute;
    }

    function reorderableOnSameSide(attribute: string): string[] {
        const side = columnPinSide(attribute);

        return orderedColumns.value
            .filter(
                (column) =>
                    column.reorderable &&
                    columnPinSide(column.attribute) === side,
            )
            .map((column) => column.attribute);
    }

    function canMoveColumn(attribute: string, direction: -1 | 1): boolean {
        if (toValue(resource).options.columnReordering === false) return false;

        const candidates = reorderableOnSameSide(attribute);
        const index = candidates.indexOf(attribute);

        return (
            index >= 0 &&
            index + direction >= 0 &&
            index + direction < candidates.length
        );
    }

    function swapColumns(attribute: string, target: string) {
        const current = toValue(resource);
        const sourceDefinition = current.columns.find(
            (column) => column.attribute === attribute,
        );
        const targetDefinition = current.columns.find(
            (column) => column.attribute === target,
        );
        if (
            current.options.columnReordering === false ||
            !sourceDefinition?.reorderable ||
            !targetDefinition?.reorderable ||
            columnPinSide(attribute) !== columnPinSide(target)
        ) {
            return;
        }

        const next = [...columnOrder.value];
        const sourceIndex = next.indexOf(attribute);
        const targetIndex = next.indexOf(target);
        if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
            return;
        }

        [next[sourceIndex], next[targetIndex]] = [
            next[targetIndex],
            next[sourceIndex],
        ];
        columnOrder.value = next;
        scheduleLayoutVisit();
    }

    function moveColumn(attribute: string, direction: -1 | 1) {
        if (!canMoveColumn(attribute, direction)) return;

        const candidates = reorderableOnSameSide(attribute);
        const index = candidates.indexOf(attribute);
        const target = candidates[index + direction];
        if (target) swapColumns(attribute, target);
    }

    function resetColumnLayout() {
        const current = toValue(resource);
        columnOrder.value = current.columns.map((column) => column.attribute);
        columnWidths.value = Object.fromEntries(
            current.columns.flatMap((column) =>
                column.width === null || column.width === undefined
                    ? []
                    : [[column.attribute, column.width]],
            ),
        );
        scheduleLayoutVisit();
    }

    function resetColumnWidth(attribute: string) {
        const definition = toValue(resource).columns.find(
            (column) => column.attribute === attribute,
        );
        if (!definition?.resizable) return;

        const next = { ...columnWidths.value };
        if (definition.width === null || definition.width === undefined) {
            delete next[attribute];
        } else {
            next[attribute] = definition.width;
        }
        columnWidths.value = next;
        scheduleLayoutVisit();
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

    const state = computed<TableState>(() => ({
        ...toValue(resource).state,
        columnOrder: columnOrder.value,
        columnWidths: columnWidths.value,
    }));
    const orderedColumns = computed(() => {
        const current = toValue(resource);
        const definitions = new Map(
            current.columns.map((column) => [column.attribute, column]),
        );

        return normalizeColumnOrder(columnOrder.value, current).flatMap(
            (attribute) => {
                const column = definitions.get(attribute);

                return column ? [column] : [];
            },
        );
    });
    const visibleColumns = computed(() =>
        orderedColumns.value.filter(
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
        clearTimeout(layoutTimer);
        latestVisit++;
        isNavigating.value = false;
    });

    return {
        clearAll,
        clearFilters,
        canMoveColumn,
        columnPinSide,
        columnStyle,
        columnWidth,
        hasActiveState,
        hasFilters,
        isNavigating,
        moveColumn,
        orderedColumns,
        patchState,
        removeFilter,
        resetColumnLayout,
        resetColumnWidth,
        resizingColumn,
        resource: computed(() => toValue(resource)),
        search,
        setFilter,
        setCursor,
        setColumnWidth,
        setPage,
        setPerPage,
        setResizingColumn,
        setSearch,
        setSort,
        state,
        swapColumns,
        toggleColumn,
        togglePinnedColumn,
        visibleColumns,
        visit,
    };
}

export type UseTable<T extends TableItem = TableItem> = ReturnType<
    typeof useTable<T>
>;
