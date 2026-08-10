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

import { useTable } from "../resources/js/useTable";

describe("useTable", () => {
    beforeEach(() => {
        visit.mockReset();
        listeners.clear();
    });

    function mountTable() {
        const resource = ref(topicResource());
        let table: ReturnType<typeof useTable<Topic>>;
        const wrapper = mount(
            defineComponent({
                setup() {
                    table = useTable(resource);
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

    it("supports explicit ascending and descending sort choices", () => {
        const { table } = mountTable();

        table.setSort("name", "asc");
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bsort%5D=name",
        );

        visit.mockReset();
        table.setSort("name", "desc");
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bsort%5D=-name",
        );
    });

    it("debounces search and resets the page", () => {
        vi.useFakeTimers();
        const { table } = mountTable();
        table.setSearch("wisdom");
        expect(visit).not.toHaveBeenCalled();
        vi.advanceTimersByTime(300);
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bsearch%5D=wisdom",
        );
        vi.useRealTimers();
    });

    it("serializes declared filter state", () => {
        const { table } = mountTable();
        table.setFilter("status", "featured");

        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bfilters%5D%5Bstatus%5D%5Bclause%5D=equals",
        );
    });

    it("toggles only declared toggleable columns", () => {
        const { table } = mountTable();
        table.toggleColumn("is_featured");
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bcolumns%5D%5Bis_featured%5D=0",
        );
        visit.mockReset();
        table.toggleColumn("missing");
        expect(visit).not.toHaveBeenCalled();
    });

    it("reflects inertia navigation state", () => {
        const { table } = mountTable();
        listeners.get("start")?.();
        expect(table.isNavigating.value).toBe(true);
        listeners.get("finish")?.();
        expect(table.isNavigating.value).toBe(false);
    });
});
