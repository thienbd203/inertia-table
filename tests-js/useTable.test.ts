import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";
import {
    inertiaReload as reload,
    inertiaVisit as visit,
    resetInertiaMock,
} from "./inertiaMock";

import { useTable } from "../resources/js/useTable";

describe("useTable", () => {
    beforeEach(() => {
        resetInertiaMock("/admin/topics?keep=yes");
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

    it("allows retry after a lazy request finishes without loading options", () => {
        const { resource, table } = mountTable();
        const applied = { enabled: true, clause: "equals", value: "featured" };
        resource.value.state.filters.status = applied;
        Object.assign(resource.value.filters[0], {
            lazy: true,
            lazyLoaded: false,
            options: [],
        });
        table.loadFilterOptions("status");
        table.loadFilterOptions("status");
        expect(reload).toHaveBeenCalledOnce();
        reload.mock.calls[0][0].onFinish();
        expect(table.isFilterOptionsLoading("status")).toBe(false);
        expect(resource.value.state.filters.status).toEqual(applied);
        table.setSort("name");
        expect(
            visit.mock.calls[0][1].headers?.[
                "X-Musing-Inertia-Table-Lazy-Filters"
            ],
        ).toBeUndefined();
        table.loadFilterOptions("status");
        expect(reload).toHaveBeenCalledTimes(1);
        visit.mock.calls[0][1].onFinish();
        expect(reload).toHaveBeenCalledTimes(2);
        resource.value.filters[0].lazyLoaded = true;
        reload.mock.calls[1][0].onFinish();
        expect(table.isFilterOptionsLoading("status")).toBe(false);
        expect(resource.value.state.filters.status).toEqual(applied);
    });

    it("clears lazy loading when the router throws before starting a request", () => {
        const { resource, table } = mountTable();
        Object.assign(resource.value.filters[0], {
            lazy: true,
            lazyLoaded: false,
            options: [],
        });
        reload.mockImplementationOnce(() => {
            throw new Error("Request unavailable");
        });
        expect(() => table.loadFilterOptions("status")).toThrow(
            "Request unavailable",
        );
        expect(table.isFilterOptionsLoading("status")).toBe(false);
        table.loadFilterOptions("status");
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it("serializes lazy reloads and retries only the unfinished filter", () => {
        const { resource, table } = mountTable();
        const first = {
            ...resource.value.filters[0],
            lazy: true,
            lazyLoaded: false,
            options: [],
        };
        resource.value.filters = [first, { ...first, attribute: "category" }];
        table.loadFilterOptions("status");
        table.loadFilterOptions("category");
        expect(reload).toHaveBeenCalledTimes(1);
        expect(table.isFilterOptionsLoading("status")).toBe(true);
        expect(table.isFilterOptionsLoading("category")).toBe(true);
        reload.mock.calls[0][0].onFinish();
        expect(table.isFilterOptionsLoading("status")).toBe(false);
        expect(reload).toHaveBeenCalledTimes(2);
        resource.value.filters[1].lazyLoaded = true;
        reload.mock.calls[1][0].onFinish();
        expect(table.isFilterOptionsLoading("category")).toBe(false);
        table.loadFilterOptions("category");
        expect(reload).toHaveBeenCalledTimes(2);
        table.loadFilterOptions("status");
        expect(reload).toHaveBeenCalledTimes(3);
        expect(
            JSON.parse(
                reload.mock.calls[2][0].headers[
                    "X-Musing-Inertia-Table-Lazy-Filters"
                ],
            ).topics.sort(),
        ).toEqual(["category", "status"]);
    });

    it.each([false, true])(
        "handles queued lazy options after disposal=%s",
        (dispose) => {
            const { resource, table, wrapper } = mountTable();
            const first = {
                ...resource.value.filters[0],
                lazy: true,
                lazyLoaded: false,
                options: [],
            };
            resource.value.filters = [
                first,
                { ...first, attribute: "category" },
            ];
            table.loadFilterOptions("status");
            table.loadFilterOptions("category");
            table.loadFilterOptions("category");
            expect(reload).toHaveBeenCalledTimes(1);
            resource.value.filters[0].lazyLoaded = true;
            if (dispose) wrapper.unmount();
            reload.mock.calls[0][0].onFinish();
            expect(reload).toHaveBeenCalledTimes(dispose ? 1 : 2);
            if (!dispose) {
                expect(
                    JSON.parse(
                        reload.mock.calls[1][0].headers[
                            "X-Musing-Inertia-Table-Lazy-Filters"
                        ],
                    ).topics,
                ).toEqual(["status", "category"]);
                resource.value.filters[1].lazyLoaded = true;
                reload.mock.calls[1][0].onFinish();
            }
            expect(table.isFilterOptionsLoading("category")).toBe(false);
        },
    );

    it("cancels stale lazy reloads before navigation and carries queued options forward", () => {
        const { resource, table } = mountTable();
        const first = {
            ...resource.value.filters[0],
            lazy: true,
            lazyLoaded: false,
            options: [],
        };
        resource.value.filters = [first, { ...first, attribute: "category" }];
        table.loadFilterOptions("status");
        const options = reload.mock.calls[0][0];
        const cancel = vi.fn(() => options.onFinish());
        options.onCancelToken({ cancel });
        table.loadFilterOptions("category");
        table.setSort("name");
        expect(cancel).toHaveBeenCalledOnce();
        expect(reload).toHaveBeenCalledTimes(1);
        expect(
            JSON.parse(
                visit.mock.calls[0][1].headers[
                    "X-Musing-Inertia-Table-Lazy-Filters"
                ],
            ).topics,
        ).toEqual(["status", "category"]);
        table.loadFilterOptions("category");
        expect(table.isFilterOptionsLoading("category")).toBe(true);
        expect(reload).toHaveBeenCalledTimes(1);
        resource.value.filters[1].lazyLoaded = true;
        visit.mock.calls[0][1].onFinish();
        expect(table.isFilterOptionsLoading("category")).toBe(false);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it("loads lazy filter options once and keeps them on later visits", () => {
        const { resource, table } = mountTable();
        resource.value.filters.push({
            attribute: "category_id",
            label: "Category",
            type: "set",
            clauses: ["in"],
            options: [],
            multiple: true,
            lazy: true,
            lazyLoaded: false,
            meta: {},
        });

        table.loadFilterOptions("category_id");

        expect(reload).toHaveBeenCalledOnce();
        expect(reload.mock.calls[0][0].only).toEqual(["topics"]);
        expect(
            JSON.parse(
                reload.mock.calls[0][0].headers[
                    "X-Musing-Inertia-Table-Lazy-Filters"
                ],
            ),
        ).toEqual({ topics: ["category_id"] });
        expect(table.isFilterOptionsLoading("category_id")).toBe(true);

        resource.value.filters[1].lazyLoaded = true;
        resource.value.filters[1].options = [{ value: 1, label: "Books" }];
        reload.mock.calls[0][0].onFinish();

        expect(table.isFilterOptionsLoading("category_id")).toBe(false);
        table.loadFilterOptions("category_id");
        expect(reload).toHaveBeenCalledOnce();

        table.setSort("name");
        expect(
            JSON.parse(
                visit.mock.calls[0][1].headers[
                    "X-Musing-Inertia-Table-Lazy-Filters"
                ],
            ),
        ).toEqual({ topics: ["category_id"] });
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

    it("pins and unpins only the selected column on the left", () => {
        const { resource, table } = mountTable();
        resource.value.columns[0].stickable = true;
        resource.value.columns[1].stickable = true;

        table.togglePinnedColumn("is_featured");
        let url = new URL(visit.mock.calls[0][0], "http://inertia-table.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual(["is_featured"]);

        visit.mockReset();
        resource.value.state.pinnedColumns = {
            left: ["name", "is_featured"],
            right: [],
        };
        table.togglePinnedColumn("is_featured");
        url = new URL(visit.mock.calls[0][0], "http://inertia-table.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual(["name"]);
    });

    it("pins and unpins only the selected column on the right", () => {
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
        let url = new URL(visit.mock.calls[0][0], "http://inertia-table.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual(["score"]);

        visit.mockReset();
        resource.value.state.pinnedColumns = {
            left: [],
            right: ["score", "__actions"],
        };
        table.togglePinnedColumn("__actions");
        url = new URL(visit.mock.calls[0][0], "http://inertia-table.local");
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual(["score"]);
    });

    it("does not let client state unpin permanently sticky columns", () => {
        const { resource, table } = mountTable();
        resource.value.columns[0].stickable = true;
        resource.value.columns[0].sticky = true;

        table.togglePinnedColumn("name");

        expect(visit).not.toHaveBeenCalled();
    });

    it("applies clamped column widths optimistically and debounces navigation", () => {
        vi.useFakeTimers();
        const { resource, table } = mountTable();
        Object.assign(resource.value.columns[0], {
            width: 240,
            minWidth: 180,
            maxWidth: 480,
            resizable: true,
        });
        resource.value.state.columnWidths = { name: 240 };

        table.setColumnWidth("name", 100);
        table.setColumnWidth("name", 900);

        expect(table.columnWidth("name")).toBe(480);
        expect(table.columnStyle("name")).toMatchObject({
            inlineSize: "480px",
            minInlineSize: "480px",
            maxInlineSize: "480px",
        });
        expect(visit).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);
        expect(visit).toHaveBeenCalledOnce();
        const url = new URL(
            visit.mock.calls[0][0],
            "http://inertia-table.local",
        );
        expect(url.searchParams.get("table[topics][columnWidths][name]")).toBe(
            "480",
        );
    });

    it("caps optimistic widths at the same safety limit as the server", () => {
        vi.useFakeTimers();
        const { resource, table } = mountTable();
        resource.value.columns[0].resizable = true;

        table.setColumnWidth("name", Number.MAX_SAFE_INTEGER);

        expect(table.columnWidth("name")).toBe(10_000);
    });

    it("folds a pending layout update into the next navigation", () => {
        vi.useFakeTimers();
        const { resource, table } = mountTable();
        resource.value.columns[0].resizable = true;

        table.setColumnWidth("name", 320);
        table.setSort("name", "desc");

        expect(visit).toHaveBeenCalledOnce();
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5BcolumnWidths%5D%5Bname%5D=320",
        );
        vi.advanceTimersByTime(300);
        expect(visit).toHaveBeenCalledOnce();
    });

    it("reorders hidden columns optimistically while preserving fixed slots", () => {
        vi.useFakeTimers();
        const { resource, table } = mountTable();
        resource.value.columns[0].reorderable = true;
        resource.value.columns[1].reorderable = true;
        resource.value.state.columns.is_featured = false;

        table.swapColumns("name", "is_featured");

        expect(
            table.orderedColumns.value.map((column) => column.attribute),
        ).toEqual(["is_featured", "name", "__actions"]);
        expect(
            table.visibleColumns.value.map((column) => column.attribute),
        ).toEqual(["name", "__actions"]);
        expect(visit).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);
        const url = new URL(
            visit.mock.calls[0][0],
            "http://inertia-table.local",
        );
        expect(url.searchParams.getAll("table[topics][columnOrder][]")).toEqual(
            ["is_featured", "name", "__actions"],
        );
    });

    it("reorders columns only within the same pinned side", () => {
        vi.useFakeTimers();
        const { resource, table } = mountTable();
        resource.value.columns[0].reorderable = true;
        resource.value.columns[1].reorderable = true;
        resource.value.state.pinnedColumns = { left: ["name"], right: [] };

        table.swapColumns("name", "is_featured");
        expect(table.state.value.columnOrder).toEqual([
            "name",
            "is_featured",
            "__actions",
        ]);
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
        let url = new URL(visit.mock.calls[0][0], "http://inertia-table.local");
        expect(url.searchParams.get("table[topics][cursor]")).toBe(
            "next-token",
        );
        expect(url.searchParams.has("table[topics][page]")).toBe(false);

        visit.mockReset();
        table.setSort("name", "desc");
        url = new URL(visit.mock.calls[0][0], "http://inertia-table.local");
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
