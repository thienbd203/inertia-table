import { router } from "@inertiajs/vue3";
import { computed, ref, watch, type Ref } from "vue";
import type {
    TableAction,
    TableItem,
    TableKey,
    TableOptions,
    TableSelection,
} from "./types";
import type { UseTable } from "./useTable";

type PendingAction<T extends TableItem> = {
    action: TableAction;
    item?: T;
};

type ActionCallbacks = {
    onCustomAction?: (
        action: TableAction,
        keys: TableKey[],
        onFinish: () => void,
        selection: TableSelection,
    ) => void;
    onSuccess?: (
        action: TableAction,
        keys: TableKey[],
        selection: TableSelection,
    ) => void;
    onError?: (
        action: TableAction,
        keys: TableKey[],
        error: unknown,
        selection: TableSelection,
    ) => void;
};

export function useActions<T extends TableItem>(
    table: UseTable<T>,
    options: TableOptions<T> = {},
    callbacks: ActionCallbacks = {},
) {
    const selectedKeys = ref<Set<TableKey>>(new Set()) as Ref<Set<TableKey>>;
    const excludedKeys = ref<Set<TableKey>>(new Set()) as Ref<Set<TableKey>>;
    const allSelected = ref(false);
    const selectionAnchorKey = ref<TableKey | null>(
        null,
    ) as Ref<TableKey | null>;
    const pendingAction = ref<PendingAction<T> | null>(
        null,
    ) as Ref<PendingAction<T> | null>;
    const isPerformingAction = ref(false);

    function rowKey(item: T, index: number): TableKey {
        return (
            options.rowKey?.(item, index) ??
            item._table?.key ??
            item.id ??
            index
        );
    }

    function isItemSelected(item: T, index: number) {
        const key = rowKey(item, index);

        return allSelected.value
            ? !excludedKeys.value.has(key)
            : selectedKeys.value.has(key);
    }

    const selectedItems = computed(() =>
        table.resource.value.results.data.filter((item, index) =>
            isItemSelected(item, index),
        ),
    );
    const selectedCount = computed(() =>
        allSelected.value
            ? Math.max(
                  table.resource.value.results.total - excludedKeys.value.size,
                  0,
              )
            : selectedKeys.value.size,
    );
    const allItemsAreSelected = computed(
        () =>
            allSelected.value &&
            table.resource.value.results.total > 0 &&
            excludedKeys.value.size === 0,
    );
    const selectionState = computed<boolean | "indeterminate">(() => {
        if (allItemsAreSelected.value) return true;

        return selectedCount.value > 0 ? "indeterminate" : false;
    });
    const selection = computed<TableSelection>(() => ({
        all: allSelected.value,
        keys: allSelected.value ? [] : [...selectedKeys.value],
        except: allSelected.value ? [...excludedKeys.value] : [],
        table: table.resource.value.name,
        state: {
            search: table.resource.value.state.search,
            filters: table.resource.value.state.filters,
        },
    }));
    const bulkActions = computed(() =>
        table.resource.value.actions.filter(
            (action) =>
                action.authorized &&
                (action.scope === "bulk" || action.scope === "both"),
        ),
    );

    watch(
        () =>
            JSON.stringify({
                search: table.resource.value.state.search,
                filters: table.resource.value.state.filters,
            }),
        () => clearSelection(),
    );

    function toggleItem(item: T, index: number, selectRange = false) {
        const key = rowKey(item, index);
        const items = table.resource.value.results.data;
        const anchorIndex = selectRange
            ? items.findIndex(
                  (candidate, candidateIndex) =>
                      rowKey(candidate, candidateIndex) ===
                      selectionAnchorKey.value,
              )
            : -1;
        const rangeStart =
            anchorIndex < 0 ? index : Math.min(anchorIndex, index);
        const rangeEnd = anchorIndex < 0 ? index : Math.max(anchorIndex, index);
        const keys = items
            .slice(rangeStart, rangeEnd + 1)
            .map((candidate, offset) => rowKey(candidate, rangeStart + offset));
        const shouldSelect = !isItemSelected(item, index);

        if (allSelected.value) {
            const next = new Set(excludedKeys.value);
            keys.forEach((candidate) =>
                shouldSelect ? next.delete(candidate) : next.add(candidate),
            );
            excludedKeys.value = next;
        } else {
            const next = new Set(selectedKeys.value);
            keys.forEach((candidate) =>
                shouldSelect ? next.add(candidate) : next.delete(candidate),
            );
            selectedKeys.value = next;
        }

        selectionAnchorKey.value = key;
    }

    function rowActionsFor(item: T) {
        return (item._table?.actions ?? []).filter(
            (action) => action.authorized && !action.hidden,
        );
    }

    function toggleAll(value?: boolean | "indeterminate") {
        if (table.resource.value.results.total === 0) {
            clearSelection();

            return;
        }

        const shouldSelectAll =
            value === true ||
            value === "indeterminate" ||
            (value === undefined && !allItemsAreSelected.value);

        if (!shouldSelectAll) {
            clearSelection();

            return;
        }

        allSelected.value = true;
        selectedKeys.value = new Set();
        excludedKeys.value = new Set();
        selectionAnchorKey.value = null;
    }

    function clearSelection() {
        allSelected.value = false;
        selectedKeys.value = new Set();
        excludedKeys.value = new Set();
        selectionAnchorKey.value = null;
    }

    function performAction(action: TableAction, item?: T) {
        if (!action.authorized || action.disabled || isPerformingAction.value) {
            return;
        }

        if (
            item === undefined &&
            (action.scope === "bulk" || action.scope === "both") &&
            selectedCount.value === 0
        ) {
            return;
        }

        if (action.confirmation) {
            pendingAction.value = { action, item };
            return;
        }

        executeAction(action, item);
    }

    function confirmAction() {
        if (!pendingAction.value || isPerformingAction.value) return;
        const { action, item } = pendingAction.value;
        executeAction(action, item, true);
    }

    function cancelAction() {
        if (isPerformingAction.value) return;
        pendingAction.value = null;
    }

    function executeAction(
        action: TableAction,
        item?: T,
        keepConfirmationOpen = false,
    ) {
        const rowIndex = item
            ? table.resource.value.results.data.indexOf(item)
            : -1;
        const resolvedSelection: TableSelection = item
            ? {
                  all: false,
                  keys: [rowKey(item, rowIndex)],
                  except: [],
                  table: table.resource.value.name,
                  state: {
                      search: table.resource.value.state.search,
                      filters: table.resource.value.state.filters,
                  },
              }
            : selection.value;
        const keys = resolvedSelection.keys;
        const data = item
            ? { id: keys[0] }
            : resolvedSelection.all
              ? { ids: [], selection: resolvedSelection }
              : { ids: keys };

        isPerformingAction.value = true;
        if (!action.endpoint) {
            callbacks.onCustomAction?.(
                action,
                keys,
                () => {
                    isPerformingAction.value = false;
                    if (keepConfirmationOpen) pendingAction.value = null;
                },
                resolvedSelection,
            );

            return;
        }

        router.visit(action.endpoint.url, {
            method: action.endpoint.method,
            data: action.endpoint.method === "get" && item ? {} : data,
            preserveScroll: true,
            onSuccess: () => {
                clearSelection();
                callbacks.onSuccess?.(action, keys, resolvedSelection);
            },
            onError: (errors) =>
                callbacks.onError?.(action, keys, errors, resolvedSelection),
            onFinish: () => {
                isPerformingAction.value = false;
                if (keepConfirmationOpen) pendingAction.value = null;
            },
        });
    }

    return {
        allItemsAreSelected,
        allSelected,
        bulkActions,
        cancelAction,
        clearSelection,
        confirmAction,
        excludedKeys,
        isItemSelected,
        isPerformingAction,
        pendingAction,
        performAction,
        rowKey,
        rowActionsFor,
        selectedCount,
        selectedItems,
        selectedKeys,
        selection,
        selectionState,
        toggleAll,
        toggleItem,
    };
}

export type UseActions<T extends TableItem = TableItem> = ReturnType<
    typeof useActions<T>
>;
