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

    const removeStartListener = router.on("start", () => {
        isNavigating.value = true;
    });
    const removeFinishListener = router.on("finish", () => {
        isNavigating.value = false;
    });

    watch(
        () => toValue(resource).state.search,
        (value) => {
            search.value = value;
        },
    );

    function visit(state: TableState, replace = true) {
        const current = toValue(resource);

        router.visit(tableUrl(page.url, current, state), {
            method: "get",
            preserveScroll: true,
            preserveState: true,
            replace,
            only: [current.name, ...current.options.reloadProps],
        });
    }

    function patchState(patch: Partial<TableState>) {
        visit({ ...toValue(resource).state, ...patch });
    }

    function setSearch(value: string) {
        if (!toValue(resource).capabilities.searchable) return;

        search.value = value;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            patchState({ search: value, page: 1 });
        }, toValue(resource).options.debounceTime);
    }

    function setSort(attribute: string) {
        const current = toValue(resource);
        const column = current.columns.find(
            (candidate) => candidate.attribute === attribute,
        );

        if (!column?.sortable) return;

        patchState({
            sort:
                current.state.sort === attribute ? `-${attribute}` : attribute,
            page: 1,
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
            delete filters[attribute];
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

        patchState({ filters, page: 1 });
    }

    function removeFilter(attribute: string) {
        const filters = { ...toValue(resource).state.filters };
        delete filters[attribute];
        patchState({ filters, page: 1 });
    }

    function clearFilters() {
        patchState({ filters: {}, page: 1 });
    }

    function clearAll() {
        search.value = "";
        clearTimeout(debounceTimer);
        patchState({ search: "", filters: {}, page: 1 });
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

    function setPage(page: number) {
        if (page < 1 || page > toValue(resource).results.lastPage) return;
        patchState({ page });
    }

    function setPerPage(perPage: number) {
        if (!toValue(resource).options.perPage.includes(perPage)) return;
        patchState({ perPage, page: 1 });
    }

    const state = computed(() => toValue(resource).state);
    const visibleColumns = computed(() =>
        toValue(resource).columns.filter(
            (column) =>
                toValue(resource).state.columns[column.attribute] !== false,
        ),
    );
    const hasFilters = computed(
        () => Object.keys(toValue(resource).state.filters).length > 0,
    );
    const hasActiveState = computed(
        () => toValue(resource).state.search !== "" || hasFilters.value,
    );

    onScopeDispose(() => {
        clearTimeout(debounceTimer);
        removeStartListener();
        removeFinishListener();
    });

    return {
        clearAll,
        clearFilters,
        hasActiveState,
        hasFilters,
        isNavigating,
        patchState,
        removeFilter,
        resource: computed(() => toValue(resource)),
        search,
        setFilter,
        setPage,
        setPerPage,
        setSearch,
        setSort,
        state,
        toggleColumn,
        visibleColumns,
        visit,
    };
}

export type UseTable<T extends TableItem = TableItem> = ReturnType<
    typeof useTable<T>
>;
