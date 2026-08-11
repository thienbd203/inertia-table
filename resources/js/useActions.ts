import { router } from "@inertiajs/vue3";
import { computed, ref, watch, type Ref } from "vue";
import type { TableAction, TableItem, TableKey, TableOptions } from "./types";
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
    ) => void;
    onSuccess?: (action: TableAction, keys: TableKey[]) => void;
    onError?: (action: TableAction, keys: TableKey[], error: unknown) => void;
};

export function useActions<T extends TableItem>(
    table: UseTable<T>,
    options: TableOptions<T> = {},
    callbacks: ActionCallbacks = {},
) {
    const selectedKeys = ref<Set<TableKey>>(new Set()) as Ref<Set<TableKey>>;
    const pendingAction = ref<PendingAction<T> | null>(
        null,
    ) as Ref<PendingAction<T> | null>;
    const isPerformingAction = ref(false);

    function rowKey(item: T, index: number): TableKey {
        return options.rowKey?.(item, index) ?? item.id ?? index;
    }

    const selectedItems = computed(() =>
        table.resource.value.results.data.filter((item, index) =>
            selectedKeys.value.has(rowKey(item, index)),
        ),
    );
    const allItemsAreSelected = computed(() => {
        const rows = table.resource.value.results.data;
        return (
            rows.length > 0 &&
            rows.every((item, index) =>
                selectedKeys.value.has(rowKey(item, index)),
            )
        );
    });
    const bulkActions = computed(() =>
        table.resource.value.actions.filter(
            (action) =>
                action.authorized &&
                (action.scope === "bulk" || action.scope === "both"),
        ),
    );

    watch(
        () =>
            table.resource.value.results.data
                .map((item, index) => rowKey(item, index))
                .join("|"),
        () => clearSelection(),
    );

    function toggleItem(item: T, index: number) {
        const key = rowKey(item, index);
        const next = new Set(selectedKeys.value);
        next.has(key) ? next.delete(key) : next.add(key);
        selectedKeys.value = next;
    }

    function isItemSelected(item: T, index: number) {
        return selectedKeys.value.has(rowKey(item, index));
    }

    function rowActionsFor(item: T) {
        return (item._table?.actions ?? []).filter(
            (action) => action.authorized && !action.hidden,
        );
    }

    function toggleAll() {
        selectedKeys.value = allItemsAreSelected.value
            ? new Set()
            : new Set(table.resource.value.results.data.map(rowKey));
    }

    function clearSelection() {
        selectedKeys.value = new Set();
    }

    function performAction(action: TableAction, item?: T) {
        if (!action.authorized || action.disabled || isPerformingAction.value) {
            return;
        }

        if (action.confirmation) {
            pendingAction.value = { action, item };
            return;
        }

        executeAction(action, item);
    }

    function confirmAction() {
        if (!pendingAction.value) return;
        const { action, item } = pendingAction.value;
        pendingAction.value = null;
        executeAction(action, item);
    }

    function cancelAction() {
        pendingAction.value = null;
    }

    function executeAction(action: TableAction, item?: T) {
        const rowIndex = item
            ? table.resource.value.results.data.indexOf(item)
            : -1;
        const keys: TableKey[] = item
            ? [rowKey(item, rowIndex)]
            : [...selectedKeys.value];
        const data = item ? { id: keys[0] } : { ids: keys };

        isPerformingAction.value = true;
        if (!action.endpoint) {
            callbacks.onCustomAction?.(action, keys, () => {
                isPerformingAction.value = false;
            });

            return;
        }

        router.visit(action.endpoint.url, {
            method: action.endpoint.method,
            data: action.endpoint.method === "get" ? {} : data,
            preserveScroll: true,
            onSuccess: () => {
                clearSelection();
                callbacks.onSuccess?.(action, keys);
            },
            onError: (errors) => callbacks.onError?.(action, keys, errors),
            onFinish: () => {
                isPerformingAction.value = false;
            },
        });
    }

    return {
        allItemsAreSelected,
        bulkActions,
        cancelAction,
        clearSelection,
        confirmAction,
        isItemSelected,
        isPerformingAction,
        pendingAction,
        performAction,
        rowActionsFor,
        selectedItems,
        selectedKeys,
        toggleAll,
        toggleItem,
    };
}

export type UseActions<T extends TableItem = TableItem> = ReturnType<
    typeof useActions<T>
>;
