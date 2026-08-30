import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import type {
    QueuedExportStatus,
    TableExport,
    TableItem,
    TableSelection,
} from "./types";
import type { UseActions } from "./useActions";
import type { UseTable } from "./useTable";

function createIdempotencyKey(): string {
    if (typeof crypto.randomUUID === "function") return crypto.randomUUID();

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

type ExportCallbacks = {
    onSuccess?: (definition: TableExport) => void;
    onQueued?: (definition: TableExport, status: QueuedExportStatus) => void;
    onError?: (definition: TableExport, error: Error) => void;
};

export function useExports<T extends TableItem>(
    table: UseTable<T>,
    actions: UseActions<T>,
    callbacks: ExportCallbacks = {},
) {
    const isExporting = ref(false);
    const error = ref<string | null>(null);
    const queuedExport = ref<QueuedExportStatus | null>(null);

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

            const response = await fetch(definition.endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/octet-stream, application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    ...csrf,
                },
                body: JSON.stringify({
                    state: table.state.value,
                    selection: selectionFor(definition),
                    idempotencyKey: createIdempotencyKey(),
                }),
            });

            if (!response.ok) {
                throw new Error(await responseMessage(response));
            }

            if (definition.queued) {
                const payload = (await response.json()) as {
                    export: QueuedExportStatus;
                };
                queuedExport.value = payload.export;
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
        queuedExport.value = null;
    }

    function updateQueuedExport(status: QueuedExportStatus) {
        queuedExport.value = status;
    }

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

function csrfHeaders(): Record<string, string> | null {
    const meta = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (meta) return { "X-CSRF-TOKEN": meta };

    const cookie = document.cookie
        .split("; ")
        .find((value) => value.startsWith("XSRF-TOKEN="))
        ?.slice("XSRF-TOKEN=".length);

    return cookie ? { "X-XSRF-TOKEN": decodeURIComponent(cookie) } : null;
}

async function responseMessage(response: Response): Promise<string> {
    try {
        const payload = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const validation = Object.values(payload.errors ?? {}).flat()[0];

        return validation ?? payload.message ?? "The export failed.";
    } catch {
        return `The export failed with status ${response.status}.`;
    }
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
