import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    router: { visit },
    usePage: () => ({ url: "/admin/topics?keep=yes" }),
}));

import { useTable } from "../resources/js/useTable";

describe("useTable", () => {
    beforeEach(() => {
        visit.mockReset();
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

    it("pins a contiguous edge group and cascades unpinning toward the center", () => {
        const { resource, table } = mountTable();
        resource.value.columns[0].stickable = true;
        resource.value.columns[1].stickable = true;

        table.togglePinnedColumn("is_featured");
        let url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual(["name", "is_featured"]);

        visit.mockReset();
        resource.value.state.pinnedColumns = {
            left: ["name", "is_featured"],
            right: [],
        };
        table.togglePinnedColumn("is_featured");
        url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual(["name"]);

        visit.mockReset();
        resource.value.state.pinnedColumns = {
            left: ["name", "is_featured"],
            right: [],
        };
        table.togglePinnedColumn("name");
        url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual([]);
    });

    it("applies the same contiguous behavior from the right edge", () => {
        const { resource, table } = mountTable();
        resource.value.columns.splice(2, 0, {
            ...resource.value.columns[1],
            attribute: "score",
            header: "Score",
            stickable: true,
        });
        resource.value.columns[3].stickable = true;
        resource.value.state.columns.score = true;

        table.togglePinnedColumn("score");
        let url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual(["score", "__actions"]);

        visit.mockReset();
        resource.value.state.pinnedColumns = {
            left: [],
            right: ["score", "__actions"],
        };
        table.togglePinnedColumn("__actions");
        url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual([]);
    });

    it("does not let client state unpin permanently sticky columns", () => {
        const { resource, table } = mountTable();
        resource.value.columns[0].stickable = true;
        resource.value.columns[0].sticky = true;

        table.togglePinnedColumn("name");

        expect(visit).not.toHaveBeenCalled();
    });

    it("ignores page navigation for unpaginated resources", () => {
        const { resource, table } = mountTable();
        resource.value.capabilities.paginated = false;

        table.setPage(2);
        table.setPerPage(10);

        expect(visit).not.toHaveBeenCalled();
    });

    it("navigates with cursors and resets them when result identity changes", () => {
        const { resource, table } = mountTable();
        resource.value.options.paginationType = "cursor";
        resource.value.state.cursor = "current-token";

        table.setCursor("next-token");
        let url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(url.searchParams.get("table[topics][cursor]")).toBe(
            "next-token",
        );
        expect(url.searchParams.has("table[topics][page]")).toBe(false);

        visit.mockReset();
        table.setSort("name", "desc");
        url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(url.searchParams.has("table[topics][cursor]")).toBe(false);
        expect(url.searchParams.get("table[topics][sort]")).toBe("-name");
    });

    it("tracks only navigation initiated by its own table instance", () => {
        const first = mountTable();
        const second = mountTable();

        first.table.setSort("name");

        expect(first.table.isNavigating.value).toBe(true);
        expect(second.table.isNavigating.value).toBe(false);

        visit.mock.calls[0][1].onFinish();
        expect(first.table.isNavigating.value).toBe(false);
    });

    it("does not finish a newer visit when an older visit completes", () => {
        const { table } = mountTable();

        table.setSort("name", "asc");
        table.setSort("name", "desc");

        visit.mock.calls[0][1].onFinish();
        expect(table.isNavigating.value).toBe(true);

        visit.mock.calls[1][1].onFinish();
        expect(table.isNavigating.value).toBe(false);
    });
});
