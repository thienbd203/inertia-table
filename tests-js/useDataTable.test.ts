import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

const { visit, listeners } = vi.hoisted(() => ({
    visit: vi.fn(),
    listeners: new Map<string, () => void>(),
}));

vi.mock("@inertiajs/vue3", () => ({
    router: {
        visit,
        on: vi.fn((event: string, callback: () => void) => {
            listeners.set(event, callback);

            return vi.fn();
        }),
    },
    usePage: () => ({ url: "/admin/topics?keep=yes" }),
}));

import { useDataTable } from "../resources/js/useDataTable";

describe("useDataTable", () => {
    beforeEach(() => {
        visit.mockReset();
        listeners.clear();
    });

    function mountTable() {
        const resource = ref(topicResource());
        let table: ReturnType<typeof useDataTable<Topic>>;
        const wrapper = mount(
            defineComponent({
                setup() {
                    table = useDataTable(resource);

                    return () => h("div");
                },
            }),
        );

        return { wrapper, resource, table: table! };
    }

    it("visits a partial reload URL when sorting", () => {
        const { table } = mountTable();

        table.setSort("name");

        expect(visit).toHaveBeenCalledOnce();
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bsort%5D=-name",
        );
        expect(visit.mock.calls[0][1].only).toEqual([
            "topics",
            "featuredCount",
        ]);
    });

    it("debounces search and resets the page", () => {
        vi.useFakeTimers();
        const { table } = mountTable();

        table.setSearch("wisdom");
        expect(visit).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);

        expect(visit).toHaveBeenCalledOnce();
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bsearch%5D=wisdom",
        );
        vi.useRealTimers();
    });

    it("tracks current-page selection and clears it on navigation", () => {
        const { table, resource } = mountTable();

        table.toggleRow(resource.value.results.data[0], 0);
        expect(table.selectedItems.value).toHaveLength(1);

        table.setPage(2);
        expect(table.selectedItems.value).toHaveLength(0);
    });

    it("reflects inertia navigation state", () => {
        const { table } = mountTable();

        listeners.get("start")?.();
        expect(table.isNavigating.value).toBe(true);

        listeners.get("finish")?.();
        expect(table.isNavigating.value).toBe(false);
    });
});
