import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { TableExport } from "../resources/js/types";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    router: { visit, on: vi.fn(() => vi.fn()) },
    usePage: () => ({ url: "/admin/topics" }),
}));

import { useActions } from "../resources/js/useActions";
import { useExports } from "../resources/js/useExports";
import { useTable } from "../resources/js/useTable";

const selectedExport: TableExport = {
    key: "selected",
    label: "Selected CSV",
    filename: "selected.csv",
    type: "csv",
    scope: "selected",
    requiresSelection: true,
    endpoint: "/_exports/selected?signature=valid",
    meta: {},
};

const queuedExport: TableExport = {
    ...selectedExport,
    key: "queued",
    label: "Queued CSV",
    scope: "filtered",
    requiresSelection: false,
    queued: true,
    endpoint: "/_exports/queued?signature=valid",
};

function mountExports(callbacks = {}) {
    const resource = ref(topicResource({ exports: [selectedExport] }));
    let actions!: ReturnType<typeof useActions<Topic>>;
    let tableExports!: ReturnType<typeof useExports<Topic>>;
    const wrapper = mount(
        defineComponent({
            setup() {
                const table = useTable(resource);
                actions = useActions(table);
                tableExports = useExports(table, actions, callbacks);

                return () => h("div");
            },
        }),
    );

    return { actions, exports: tableExports, resource, wrapper };
}

describe("useExports", () => {
    beforeEach(() => {
        visit.mockReset();
        const meta = document.createElement("meta");
        meta.name = "csrf-token";
        meta.content = "csrf-value";
        document.head.append(meta);
        vi.stubGlobal("fetch", vi.fn());
        vi.spyOn(URL, "createObjectURL").mockReturnValue("blob:export");
        vi.spyOn(URL, "revokeObjectURL").mockImplementation(() => {});
        vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(
            () => {},
        );
    });

    afterEach(() => {
        document.head.querySelector('meta[name="csrf-token"]')?.remove();
        document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it("posts normalized state and selection, downloads the response, and preserves selection", async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response("ID,Name\n1,Alpha", {
                status: 200,
                headers: {
                    "content-disposition":
                        'attachment; filename="server-name.csv"',
                },
            }),
        );
        const onSuccess = vi.fn();
        const { actions, exports } = mountExports({ onSuccess });
        actions.toggleItem(topicResource().results.data[0], 0);

        await exports.perform(selectedExport);

        expect(fetch).toHaveBeenCalledWith(
            selectedExport.endpoint,
            expect.objectContaining({
                method: "POST",
                credentials: "same-origin",
                headers: expect.objectContaining({
                    "X-CSRF-TOKEN": "csrf-value",
                }),
            }),
        );
        const payload = JSON.parse(
            String(vi.mocked(fetch).mock.calls[0][1]?.body),
        );
        expect(payload.selection).toMatchObject({
            all: false,
            keys: [1],
            table: "topics",
            state: { search: "", sort: "name" },
        });
        expect(actions.selectedCount.value).toBe(1);
        expect(HTMLAnchorElement.prototype.click).toHaveBeenCalled();
        expect(onSuccess).toHaveBeenCalledWith(selectedExport);
        expect(exports.isExporting.value).toBe(false);
    });

    it("does not start a selected export until at least one row is selected", async () => {
        const { exports } = mountExports();

        await exports.perform(selectedExport);

        expect(fetch).not.toHaveBeenCalled();
    });

    it("surfaces server validation errors through state and the error callback", async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(
                JSON.stringify({
                    message: "Invalid export",
                    errors: { export: ["Adapter unavailable."] },
                }),
                {
                    status: 422,
                    headers: { "content-type": "application/json" },
                },
            ),
        );
        const onError = vi.fn();
        const { actions, exports } = mountExports({ onError });
        actions.toggleAll(true);

        await exports.perform(selectedExport);

        expect(exports.error.value).toBe("Adapter unavailable.");
        expect(onError).toHaveBeenCalledWith(selectedExport, expect.any(Error));
        expect(actions.allSelected.value).toBe(true);
    });

    it("reports a missing CSRF meta tag without sending a request", async () => {
        document.head.querySelector('meta[name="csrf-token"]')?.remove();
        const { actions, exports } = mountExports();
        actions.toggleAll(true);

        await exports.perform(selectedExport);

        expect(fetch).not.toHaveBeenCalled();
        expect(exports.error.value).toContain("Missing CSRF token");
    });

    it("falls back to Laravel's encoded XSRF cookie", async () => {
        document.head.querySelector('meta[name="csrf-token"]')?.remove();
        document.cookie = "XSRF-TOKEN=cookie%20token; path=/";
        vi.mocked(fetch).mockResolvedValue(
            new Response("csv", { status: 200 }),
        );
        const { actions, exports } = mountExports();
        actions.toggleAll(true);

        await exports.perform(selectedExport);

        expect(vi.mocked(fetch).mock.calls[0][1]?.headers).toMatchObject({
            "X-XSRF-TOKEN": "cookie token",
        });
    });

    it("tracks queued dispatch state, emits it, and follows an explicit redirect without polling", async () => {
        const status = {
            id: "export-1",
            status: "dispatched" as const,
            filename: "topics.csv",
            url: null,
            redirect: "/exports/history",
        };
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ export: status }), {
                status: 202,
                headers: { "content-type": "application/json" },
            }),
        );
        const onQueued = vi.fn();
        const { exports } = mountExports({ onQueued });

        await exports.perform(queuedExport);

        const payload = JSON.parse(
            String(vi.mocked(fetch).mock.calls[0][1]?.body),
        );
        expect(payload.idempotencyKey).toBeTypeOf("string");
        expect(payload).not.toHaveProperty("selection");
        expect(vi.mocked(fetch).mock.calls[0][1]?.headers).toMatchObject({
            Accept: "application/json, application/octet-stream",
        });
        expect(exports.queuedExport.value).toEqual(status);
        expect(onQueued).toHaveBeenCalledWith(queuedExport, status);
        expect(visit).toHaveBeenCalledWith("/exports/history", {
            method: "get",
        });
        expect(HTMLAnchorElement.prototype.click).not.toHaveBeenCalled();

        exports.updateQueuedExport({
            ...status,
            status: "ready",
            url: "/file",
        });
        expect(exports.queuedExport.value?.status).toBe("ready");
    });

    it("polls queued exports until the file is ready", async () => {
        vi.useFakeTimers();
        const dispatched = {
            id: "export-2",
            status: "dispatched" as const,
            filename: "topics.csv",
            url: null,
            statusEndpoint: "/_exports/queued/export-2?signature=valid",
        };
        const processing = {
            ...dispatched,
            status: "processing" as const,
        };
        const ready = {
            ...dispatched,
            status: "ready" as const,
            url: "/downloads/export-2",
        };
        vi.mocked(fetch)
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ export: dispatched }), {
                    status: 202,
                    headers: { "content-type": "application/json" },
                }),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ export: processing }), {
                    status: 200,
                    headers: { "content-type": "application/json" },
                }),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ export: ready }), {
                    status: 200,
                    headers: { "content-type": "application/json" },
                }),
            );
        const { exports, wrapper } = mountExports();

        await exports.perform(queuedExport);
        await vi.advanceTimersByTimeAsync(1_500);

        expect(exports.queuedExport.value?.status).toBe("processing");
        expect(fetch).toHaveBeenNthCalledWith(
            2,
            dispatched.statusEndpoint,
            expect.objectContaining({ method: "GET" }),
        );

        await vi.advanceTimersByTimeAsync(1_500);

        expect(exports.queuedExport.value).toEqual(ready);
        expect(fetch).toHaveBeenCalledTimes(3);

        await vi.advanceTimersByTimeAsync(3_000);
        expect(fetch).toHaveBeenCalledTimes(3);
        wrapper.unmount();
    });
});
