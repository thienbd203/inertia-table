import { mount } from "@vue/test-utils";
import { defineComponent, h, nextTick, ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { TableResource, TableView } from "../resources/js/types";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    router: { visit, on: vi.fn(() => vi.fn()) },
    usePage: () => ({ url: "/admin/topics?team=one" }),
}));

import { useTable } from "../resources/js/useTable";
import { useViews } from "../resources/js/useViews";

function savedView(overrides: Partial<TableView> = {}): TableView {
    return {
        id: 7,
        name: "Featured",
        state: {
            schemaVersion: 1,
            sort: "name",
            filters: {
                status: {
                    enabled: false,
                    clause: "equals",
                    value: null,
                },
            },
            columns: {
                name: true,
                is_featured: true,
                __actions: true,
            },
            pinnedColumns: { left: [], right: [] },
            perPage: 25,
        },
        isDefault: true,
        isShared: false,
        version: 3,
        canUpdate: true,
        canDelete: true,
        canShare: true,
        canDefault: true,
        endpoints: {
            update: "/_views/7?signature=update",
            delete: "/_views/7?signature=delete",
            default: "/_views/7/default?signature=default",
            share: "/_views/7/share?signature=share",
        },
        ...overrides,
    };
}

function resourceWithViews(
    overrides: Partial<TableResource<Topic>> = {},
): TableResource<Topic> {
    const resource = topicResource(overrides);
    resource.state.view = 7;
    resource.views = {
        items: [savedView()],
        selected: 7,
        includeSearch: false,
        canCreate: true,
        storeEndpoint: "/_views?signature=store",
    };

    return resource;
}

describe("useViews", () => {
    beforeEach(() => visit.mockReset());

    function mountViews(initial = resourceWithViews()) {
        const resource = ref(initial);
        let views!: ReturnType<typeof useViews<Topic>>;
        const wrapper = mount(
            defineComponent({
                setup() {
                    const table = useTable(resource);
                    views = useViews(table);

                    return () => h("div");
                },
            }),
        );

        return { resource, views, wrapper };
    }

    it("detects dirty state and resets to the selected normalized view", async () => {
        const { resource, views } = mountViews();
        expect(views.isDirty.value).toBe(false);

        resource.value = {
            ...resource.value,
            state: { ...resource.value.state, sort: "-name" },
        };
        await nextTick();

        expect(views.isDirty.value).toBe(true);
        views.reset();

        expect(visit).toHaveBeenCalledWith(
            expect.stringContaining("table%5Btopics%5D%5Bview%5D=7"),
            expect.objectContaining({ method: "get" }),
        );
        const url = new URL(
            visit.mock.calls[0][0],
            "http://inertia-table.local",
        );
        expect(url.searchParams.get("team")).toBe("one");
        expect(url.searchParams.get("table[topics][sort]")).toBe("name");
        expect(
            url.searchParams.get("table[topics][filters][status][enabled]"),
        ).toBe("0");
    });

    it("sends normalized state for create and update without ephemeral search", () => {
        const { views } = mountViews();
        views.create("New view");

        expect(visit).toHaveBeenCalledWith(
            "/_views?signature=store",
            expect.objectContaining({
                method: "post",
                data: expect.objectContaining({
                    name: "New view",
                    state: expect.not.objectContaining({
                        search: expect.anything(),
                    }),
                }),
            }),
        );

        visit.mock.calls[0][1]?.onFinish?.({} as never);
        visit.mockClear();
        views.update(savedView());
        expect(visit).toHaveBeenCalledWith(
            "/_views/7?signature=update",
            expect.objectContaining({
                method: "patch",
                data: expect.objectContaining({ version: 3 }),
            }),
        );
    });

    it("persists search only when the server enables it", () => {
        const resource = resourceWithViews();
        resource.state.search = "Gamma";
        resource.views!.includeSearch = true;
        const { views } = mountViews(resource);

        expect(views.persistableState().search).toBe("Gamma");
    });

    it("persists and restores normalized pinned columns", () => {
        const resource = resourceWithViews();
        resource.state.pinnedColumns = {
            left: ["name"],
            right: ["__actions"],
        };
        resource.views!.items[0].state.pinnedColumns = {
            left: [],
            right: ["__actions"],
        };
        const { views } = mountViews(resource);

        expect(views.persistableState().pinnedColumns).toEqual({
            left: ["name"],
            right: ["__actions"],
        });

        views.reset();
        const url = new URL(
            visit.mock.calls[0][0],
            "http://inertia-table.local",
        );
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual(["__actions"]);
        expect(
            url.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual([""]);
    });

    it("uses independent signed endpoints and lock versions for mutations", () => {
        const { views } = mountViews();
        const view = savedView();

        views.rename(view, "Renamed");
        expect(visit.mock.calls.at(-1)?.[1]).toMatchObject({
            method: "patch",
            data: { name: "Renamed", version: 3 },
        });
        visit.mock.calls.at(-1)?.[1]?.onFinish?.({} as never);

        views.setDefault(view);
        expect(visit.mock.calls.at(-1)?.[1]).toMatchObject({
            method: "post",
            data: { version: 3 },
        });
        visit.mock.calls.at(-1)?.[1]?.onFinish?.({} as never);

        views.setShared(view, true);
        expect(visit.mock.calls.at(-1)?.[1]).toMatchObject({
            method: "post",
            data: { shared: true, version: 3 },
        });
        visit.mock.calls.at(-1)?.[1]?.onFinish?.({} as never);

        views.remove(view);
        expect(visit.mock.calls.at(-1)?.[1]).toMatchObject({
            method: "delete",
            data: { version: 3 },
        });
    });
});
