import { router } from "@inertiajs/vue3";
import { computed, onScopeDispose, ref, watch, type Ref } from "vue";
import { createIdempotencyKey, csrfHeaders, responseMessage } from "./http";
import type {
    QueuedActionStatus,
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

type ActiveQueuedAction = {
    action: TableAction;
    keys: TableKey[];
    selection: TableSelection;
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
    onQueued?: (
        action: TableAction,
        status: QueuedActionStatus,
        selection: TableSelection,
    ) => void;
    onProgress?: (
        action: TableAction,
        status: QueuedActionStatus,
        selection: TableSelection,
    ) => void;
};

const queuedActionPollDelay = 1_500;

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
    const queuedAction = ref<QueuedActionStatus | null>(null);
    const actionError = ref<string | null>(null);
    let activeQueuedAction: ActiveQueuedAction | null = null;
    let pollingTimer: number | null = null;
    let pollingGeneration = 0;

    function stopPolling() {
        pollingGeneration += 1;

        if (pollingTimer !== null) {
            window.clearTimeout(pollingTimer);
            pollingTimer = null;
        }
    }

    function queuedActionIsTerminal(status: QueuedActionStatus) {
        return ["completed", "failed", "expired"].includes(status.status);
    }

    function schedulePoll(endpoint: string, generation: number) {
        pollingTimer = window.setTimeout(() => {
            void pollQueuedAction(endpoint, generation);
        }, queuedActionPollDelay);
    }

    async function pollQueuedAction(endpoint: string, generation: number) {
        try {
            const response = await fetch(endpoint, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (generation !== pollingGeneration) return;

            if (!response.ok) {
                throw new Error(
                    await responseMessage(
                        response,
                        "The action status could not be checked.",
                    ),
                );
            }

            const payload = (await response.json()) as {
                action: QueuedActionStatus;
            };

            if (generation !== pollingGeneration) return;

            updateQueuedAction(payload.action);
        } catch (reason) {
            if (generation !== pollingGeneration) return;

            const resolved =
                reason instanceof Error
                    ? reason
                    : new Error("The action status could not be checked.");
            actionError.value = resolved.message;

            if (queuedAction.value) {
                queuedAction.value = {
                    ...queuedAction.value,
                    status: "failed",
                    message: resolved.message,
                };
            }

            if (activeQueuedAction) {
                callbacks.onError?.(
                    activeQueuedAction.action,
                    activeQueuedAction.keys,
                    resolved,
                    activeQueuedAction.selection,
                );
            }

            activeQueuedAction = null;
            isPerformingAction.value = false;
            stopPolling();
        }
    }

    function startPolling(status: QueuedActionStatus) {
        stopPolling();

        if (
            status.redirect ||
            queuedActionIsTerminal(status) ||
            !status.statusEndpoint
        ) {
            return;
        }

        const generation = pollingGeneration;
        schedulePoll(status.statusEndpoint, generation);
    }

    function updateQueuedAction(status: QueuedActionStatus) {
        const previous = queuedAction.value;
        queuedAction.value = status;

        if (activeQueuedAction && status.status === "processing") {
            callbacks.onProgress?.(
                activeQueuedAction.action,
                status,
                activeQueuedAction.selection,
            );
        }

        if (queuedActionIsTerminal(status)) {
            stopPolling();

            if (activeQueuedAction && previous?.status !== status.status) {
                const { action, keys, selection } = activeQueuedAction;

                if (status.status === "completed") {
                    callbacks.onSuccess?.(action, keys, selection);

                    if (status.redirect) {
                        router.visit(status.redirect, { method: "get" });
                    } else {
                        router.reload({
                            only: [
                                table.resource.value.name,
                                ...table.resource.value.options.reloadProps,
                            ],
                        });
                    }
                } else {
                    const error = new Error(
                        status.message ?? "The queued action failed.",
                    );
                    actionError.value = error.message;
                    callbacks.onError?.(action, keys, error, selection);
                }
            }

            activeQueuedAction = null;
            isPerformingAction.value = false;

            return;
        }

        startPolling(status);
    }

    function clearQueuedAction() {
        queuedAction.value = null;
        actionError.value = null;
    }

    function clearActionError() {
        actionError.value = null;
    }

    function rowKey(item: T, index: number): TableKey {
        return (
            options.rowKey?.(item, index) ??
            item._table?.key ??
            item.id ??
            index
        );
    }

    function isItemSelected(item: T, index: number) {
        if (!isItemSelectable(item)) return false;

        const key = rowKey(item, index);

        return allSelected.value
            ? !excludedKeys.value.has(key)
            : selectedKeys.value.has(key);
    }

    function isItemSelectable(item: T) {
        return item._table?.selectable !== false;
    }

    const selectableTotal = computed(
        () =>
            table.resource.value.results.selectableTotal ??
            table.resource.value.results.total ??
            0,
    );

    const selectedItems = computed(() =>
        table.resource.value.results.data.filter((item, index) =>
            isItemSelected(item, index),
        ),
    );
    const selectedCount = computed(() =>
        allSelected.value
            ? Math.max(selectableTotal.value - excludedKeys.value.size, 0)
            : selectedKeys.value.size,
    );
    const allItemsAreSelected = computed(
        () =>
            allSelected.value &&
            selectableTotal.value > 0 &&
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
            sort: table.resource.value.state.sort,
            filters: table.resource.value.state.filters,
        },
    }));
    const pendingConfirmation = computed(() => {
        const pending = pendingAction.value;
        const confirmation = pending?.action.confirmation;

        if (!pending || !confirmation) return null;

        const count = pending.item ? 1 : selectedCount.value;
        const attributes: Record<string, unknown> = pending.item
            ? { ...pending.item }
            : {};
        const resolveVariant = (
            value: string | [string, string, string?],
        ): string => {
            if (typeof value === "string") return value;

            if (pending.item || count === 1) return value[0];
            if (selection.value.all) return value[2] ?? value[1];

            return value[1];
        };
        const interpolate = (copy: string | [string, string, string?]) =>
            resolveVariant(copy).replace(
                /:([A-Za-z_][A-Za-z0-9_]*)\b/g,
                (placeholder, attribute: string) => {
                    if (attribute === "count") return String(count);

                    const replacement = attributes[attribute];

                    return typeof replacement === "string" ||
                        typeof replacement === "number" ||
                        typeof replacement === "boolean"
                        ? String(replacement)
                        : placeholder;
                },
            );

        return {
            title: interpolate(confirmation.title),
            message: interpolate(confirmation.message),
            confirmLabel: interpolate(confirmation.confirmLabel),
            cancelLabel: interpolate(confirmation.cancelLabel),
        };
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
            JSON.stringify({
                search: table.resource.value.state.search,
                filters: table.resource.value.state.filters,
            }),
        () => clearSelection(),
    );

    function toggleItem(item: T, index: number, selectRange = false) {
        if (!isItemSelectable(item)) return;

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
            .map((candidate, offset) => ({
                item: candidate,
                key: rowKey(candidate, rangeStart + offset),
            }))
            .filter(({ item }) => isItemSelectable(item))
            .map(({ key }) => key);
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
        if (selectableTotal.value === 0) {
            clearSelection();

            return;
        }

        const shouldSelectAll =
            selectionState.value === "indeterminate" ||
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
                      sort: table.resource.value.state.sort,
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
        actionError.value = null;
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

        if (action.queued) {
            void executeQueuedAction(
                action,
                keys,
                resolvedSelection,
                keepConfirmationOpen,
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

    async function executeQueuedAction(
        action: TableAction,
        keys: TableKey[],
        resolvedSelection: TableSelection,
        keepConfirmationOpen: boolean,
    ) {
        try {
            const csrf = csrfHeaders();

            if (!csrf) {
                throw new Error(
                    "Missing CSRF token. Add a csrf-token meta tag or enable Laravel's XSRF-TOKEN cookie.",
                );
            }

            const response = await fetch(action.endpoint!.url, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    ...csrf,
                },
                body: JSON.stringify({
                    ids: keys,
                    selection: resolvedSelection,
                    idempotencyKey: createIdempotencyKey(),
                }),
            });

            if (!response.ok) {
                throw new Error(
                    await responseMessage(response, "The action failed."),
                );
            }

            const payload = (await response.json()) as {
                action: QueuedActionStatus;
            };
            activeQueuedAction = { action, keys, selection: resolvedSelection };
            clearSelection();
            updateQueuedAction(payload.action);
            callbacks.onQueued?.(action, payload.action, resolvedSelection);

            if (payload.action.redirect) {
                activeQueuedAction = null;
                isPerformingAction.value = false;
                router.visit(payload.action.redirect, { method: "get" });
            }
        } catch (reason) {
            const resolved =
                reason instanceof Error
                    ? reason
                    : new Error("The action failed.");
            actionError.value = resolved.message;
            callbacks.onError?.(action, keys, resolved, resolvedSelection);
        } finally {
            if (!activeQueuedAction) isPerformingAction.value = false;
            if (keepConfirmationOpen) pendingAction.value = null;
        }
    }

    onScopeDispose(stopPolling);

    return {
        allItemsAreSelected,
        allSelected,
        actionError,
        bulkActions,
        cancelAction,
        clearActionError,
        clearQueuedAction,
        clearSelection,
        confirmAction,
        excludedKeys,
        isItemSelected,
        isItemSelectable,
        isPerformingAction,
        pendingAction,
        pendingConfirmation,
        performAction,
        rowKey,
        rowActionsFor,
        selectedCount,
        selectedItems,
        selectedKeys,
        selectableTotal,
        selection,
        selectionState,
        toggleAll,
        toggleItem,
        queuedAction,
        updateQueuedAction,
    };
}

export type UseActions<T extends TableItem = TableItem> = ReturnType<
    typeof useActions<T>
>;
