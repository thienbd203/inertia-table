import { router } from "@inertiajs/vue3";
import { computed, ref, watch, type Ref } from "vue";
import type { TableAction, TableItem, TableKey, TableOptions } from "./types";
import type { UseTable } from "./useTable";

type PendingAction<T extends TableItem> = {
    action: TableAction;
    item?: T;
};

export function useActions<T extends TableItem>(
    table: UseTable<T>,
    options: TableOptions<T> = {},
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
    const rowActions = computed(() =>
        table.resource.value.actions.filter(
            (action) =>
                action.authorized &&
                (action.scope === "row" || action.scope === "both"),
        ),
    );
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

    function toggleAll() {
        selectedKeys.value = allItemsAreSelected.value
            ? new Set()
            : new Set(table.resource.value.results.data.map(rowKey));
    }

    function clearSelection() {
        selectedKeys.value = new Set();
    }

    function performAction(action: TableAction, item?: T) {
        if (!action.authorized) return;

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
        const data = item
            ? { id: rowKey(item, rowIndex) }
            : { ids: [...selectedKeys.value] };

        isPerformingAction.value = true;
        router.visit(action.endpoint.url, {
            method: action.endpoint.method,
            data,
            preserveScroll: true,
            onSuccess: clearSelection,
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
        rowActions,
        selectedItems,
        selectedKeys,
        toggleAll,
        toggleItem,
    };
}
