import { router, usePage } from "@inertiajs/vue3";
import {
    computed,
    onScopeDispose,
    ref,
    toValue,
    watch,
    type MaybeRefOrGetter,
} from "vue";
import type {
    DataTableOptions,
    TableItem,
    TableKey,
    TableResource,
    TableState,
} from "./types";
import { tableUrl } from "./url";

export function useDataTable<T extends TableItem>(
    resource: MaybeRefOrGetter<TableResource<T>>,
    options: DataTableOptions<T> = {},
) {
    const page = usePage();
    const search = ref(toValue(resource).state.search);
    const selected = ref<Set<TableKey>>(new Set());
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
        selected.value = new Set();

        router.visit(tableUrl(page.url, current, state), {
            method: "get",
            preserveScroll: true,
            preserveState: true,
            replace,
            only: [current.name, ...current.reloadProps],
        });
    }

    function patchState(patch: Partial<TableState>) {
        const state = toValue(resource).state;

        visit({ ...state, ...patch });
    }

    function setSearch(value: string) {
        search.value = value;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            patchState({ search: value, page: 1 });
        }, toValue(resource).debounceTime);
    }

    function setSort(attribute: string) {
        const current = toValue(resource);
        const column = current.columns.find(
            (candidate) => candidate.attribute === attribute,
        );

        if (!column?.sortable) {
            return;
        }

        const sort =
            current.state.sort === attribute ? `-${attribute}` : attribute;
        patchState({ sort, page: 1 });
    }

    function setFilter(attribute: string, value: unknown) {
        const filters = { ...toValue(resource).state.filters };

        if (value === null || value === undefined || value === "") {
            delete filters[attribute];
        } else {
            filters[attribute] = value;
        }

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

    function setPage(page: number) {
        if (page < 1 || page > toValue(resource).results.lastPage) {
            return;
        }

        patchState({ page });
    }

    function setPerPage(perPage: number) {
        if (!toValue(resource).perPageOptions.includes(perPage)) {
            return;
        }

        patchState({ perPage, page: 1 });
    }

    function rowKey(item: T, index: number): TableKey {
        return options.rowKey?.(item, index) ?? item.id ?? index;
    }

    function toggleRow(item: T, index: number) {
        const key = rowKey(item, index);
        const next = new Set(selected.value);

        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }

        selected.value = next;
    }

    function isRowSelected(item: T, index: number) {
        return selected.value.has(rowKey(item, index));
    }

    function togglePage() {
        const current = toValue(resource);
        const keys = current.results.data.map(rowKey);
        const allSelected =
            keys.length > 0 && keys.every((key) => selected.value.has(key));
        selected.value = allSelected ? new Set() : new Set(keys);
    }

    function clearSelection() {
        selected.value = new Set();
    }

    const selectedItems = computed(() =>
        toValue(resource).results.data.filter((item, index) =>
            selected.value.has(rowKey(item, index)),
        ),
    );
    const hasActiveFilters = computed(
        () =>
            toValue(resource).state.search !== "" ||
            Object.keys(toValue(resource).state.filters).length > 0,
    );
    const allPageSelected = computed(() => {
        const current = toValue(resource);

        return (
            current.results.data.length > 0 &&
            current.results.data.every((item, index) =>
                selected.value.has(rowKey(item, index)),
            )
        );
    });

    onScopeDispose(() => {
        clearTimeout(debounceTimer);
        removeStartListener();
        removeFinishListener();
    });

    return {
        allPageSelected,
        clearAll,
        clearFilters,
        clearSelection,
        hasActiveFilters,
        isNavigating,
        isRowSelected,
        search,
        selected,
        selectedItems,
        setFilter,
        setPage,
        setPerPage,
        setSearch,
        setSort,
        togglePage,
        toggleRow,
    };
}
