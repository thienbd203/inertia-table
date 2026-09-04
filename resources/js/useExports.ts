import { onScopeDispose, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { createIdempotencyKey, csrfHeaders, responseMessage } from "./http";
import type {
    QueuedExportStatus,
    TableExport,
    TableItem,
    TableSelection,
} from "./types";
import type { UseActions } from "./useActions";
import type { UseTable } from "./useTable";

type ExportCallbacks = {
    onSuccess?: (definition: TableExport) => void;
    onQueued?: (definition: TableExport, status: QueuedExportStatus) => void;
    onError?: (definition: TableExport, error: Error) => void;
};

const queuedExportPollDelay = 1_500;

export function useExports<T extends TableItem>(
    table: UseTable<T>,
    actions: UseActions<T>,
    callbacks: ExportCallbacks = {},
) {
    const isExporting = ref(false);
    const error = ref<string | null>(null);
    const queuedExport = ref<QueuedExportStatus | null>(null);
    let pollingTimer: number | null = null;
    let pollingGeneration = 0;

    function stopPolling() {
        pollingGeneration += 1;

        if (pollingTimer !== null) {
            window.clearTimeout(pollingTimer);
            pollingTimer = null;
        }
    }

    function terminal(status: QueuedExportStatus) {
        return ["ready", "failed", "expired"].includes(status.status);
    }

    function schedulePoll(endpoint: string, generation: number) {
        pollingTimer = window.setTimeout(() => {
            void pollQueuedExport(endpoint, generation);
        }, queuedExportPollDelay);
    }

    async function pollQueuedExport(endpoint: string, generation: number) {
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
                        "The export status could not be checked.",
                    ),
                );
            }

            const payload = (await response.json()) as {
                export: QueuedExportStatus;
            };

            if (generation !== pollingGeneration) return;

            queuedExport.value = payload.export;

            if (terminal(payload.export)) {
                stopPolling();

                return;
            }

            schedulePoll(payload.export.statusEndpoint ?? endpoint, generation);
        } catch (reason) {
            if (generation !== pollingGeneration) return;

            const current = queuedExport.value;
            const resolved =
                reason instanceof Error
                    ? reason
                    : new Error("The export status could not be checked.");

            if (current) {
                queuedExport.value = {
                    ...current,
                    status: "failed",
                    message: resolved.message,
                };
            }

            stopPolling();
        }
    }

    function startPolling(status: QueuedExportStatus) {
        stopPolling();

        if (status.redirect || terminal(status) || !status.statusEndpoint) {
            return;
        }

        const generation = pollingGeneration;
        schedulePoll(status.statusEndpoint, generation);
    }

    function selectionFor(definition: TableExport): TableSelection | null {
        if (!definition.requiresSelection) return null;
        if (actions.selectedCount.value === 0) return null;

        return actions.selection.value;
    }

    async function perform(definition: TableExport) {
        if (
            isExporting.value ||
            (definition.requiresSelection && actions.selectedCount.value === 0)
        ) {
            return;
        }

        isExporting.value = true;
        error.value = null;

        try {
            const csrf = csrfHeaders();

            if (!csrf) {
                throw new Error(
                    "Missing CSRF token. Add a csrf-token meta tag or enable Laravel's XSRF-TOKEN cookie.",
                );
            }

            const selection = selectionFor(definition);
            const response = await fetch(definition.endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json, application/octet-stream",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    ...csrf,
                },
                body: JSON.stringify({
                    state: table.state.value,
                    ...(selection === null ? {} : { selection }),
                    idempotencyKey: createIdempotencyKey(),
                }),
            });

            if (!response.ok) {
                throw new Error(
                    await responseMessage(response, "The export failed."),
                );
            }

            if (definition.queued) {
                const payload = (await response.json()) as {
                    export: QueuedExportStatus;
                };
                updateQueuedExport(payload.export);
                callbacks.onQueued?.(definition, payload.export);

                if (payload.export.redirect) {
                    router.visit(payload.export.redirect, { method: "get" });
                }

                return;
            }

            const blob = await response.blob();
            const href = URL.createObjectURL(blob);
            const anchor = document.createElement("a");
            anchor.href = href;
            anchor.download = responseFilename(response) ?? definition.filename;
            anchor.hidden = true;
            document.body.append(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(() => URL.revokeObjectURL(href), 0);
            callbacks.onSuccess?.(definition);
        } catch (reason) {
            const resolved =
                reason instanceof Error
                    ? reason
                    : new Error("The export could not be downloaded.");
            error.value = resolved.message;
            callbacks.onError?.(definition, resolved);
        } finally {
            isExporting.value = false;
        }
    }

    function clearError() {
        error.value = null;
    }

    function clearQueuedExport() {
        stopPolling();
        queuedExport.value = null;
    }

    function updateQueuedExport(status: QueuedExportStatus) {
        queuedExport.value = status;
        startPolling(status);
    }

    onScopeDispose(stopPolling);

    return {
        clearError,
        clearQueuedExport,
        error,
        isExporting,
        perform,
        queuedExport,
        updateQueuedExport,
    };
}

function responseFilename(response: Response): string | null {
    const disposition = response.headers.get("content-disposition");
    const encoded = disposition?.match(/filename\*=UTF-8''([^;]+)/i)?.[1];

    if (encoded) return decodeURIComponent(encoded);

    return disposition?.match(/filename="?([^";]+)"?/i)?.[1] ?? null;
}

export type UseExports<T extends TableItem = TableItem> = ReturnType<
    typeof useExports<T>
>;
