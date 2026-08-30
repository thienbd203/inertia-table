import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { TableExport } from "../resources/js/types";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

vi.mock("@inertiajs/vue3", () => ({
    router: { visit: vi.fn(), on: vi.fn(() => vi.fn()) },
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
});
