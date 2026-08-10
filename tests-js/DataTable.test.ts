import { flushPromises, mount } from "@vue/test-utils";
import { h } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { topicResource } from "./fixtures";

const { listeners } = vi.hoisted(() => ({
    listeners: new Map<string, () => void>(),
}));

vi.mock("@inertiajs/vue3", () => ({
    Link: "a",
    router: {
        visit: vi.fn(),
        on: vi.fn((event: string, callback: () => void) => {
            listeners.set(event, callback);

            return vi.fn();
        }),
    },
    usePage: () => ({ url: "/admin/topics" }),
}));

import DataTable from "../resources/js/DataTable.vue";
import { setIconResolver } from "../resources/js/icons";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "../resources/js/components/ui/dropdown-menu";

async function openDropdown(
    wrapper: ReturnType<typeof mount>,
    triggerLabel: string,
) {
    const dropdown = wrapper
        .findAllComponents(UiDropdownMenu)
        .find(
            (candidate) =>
                candidate.findComponent(UiDropdownMenuTrigger).text() ===
                triggerLabel,
        );

    expect(dropdown).toBeDefined();
    await dropdown!.findComponent(UiDropdownMenuTrigger).trigger("click");
    await flushPromises();
}

describe("DataTable shadcn renderer", () => {
    beforeEach(() => {
        listeners.clear();
        setIconResolver(null);
    });

    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("renders the bundled shadcn-vue primitives", async () => {
        const wrapper = mount(DataTable, {
            props: {
                resource: topicResource(),
            },
            attachTo: document.body,
        });

        expect(wrapper.find('[data-slot="table"]').exists()).toBe(true);
        expect(wrapper.find('[data-slot="table-header"]').exists()).toBe(true);
        expect(wrapper.find('[data-slot="input"]').exists()).toBe(true);
        expect(wrapper.find('[data-slot="checkbox"]').exists()).toBe(true);
        expect(wrapper.find('[data-slot="select-trigger"]').exists()).toBe(
            true,
        );
        expect(wrapper.findAll('[data-slot="button"]').length).toBeGreaterThan(
            0,
        );
        expect(wrapper.get('a[href="/topics/1"]').text()).toBe("Alpha");
        expect(wrapper.text()).toContain("Edit");
        expect(wrapper.text()).toContain("Actions");
        expect(wrapper.text()).toContain("Filters");
        expect(wrapper.text()).toContain("Columns");

        await openDropdown(wrapper, "Actions");
        expect(document.body.textContent).toContain("Delete");
    });

    it("allows one action renderer to be replaced by its dynamic slot", async () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            slots: {
                "action(delete)": ({
                    selectedItems,
                }: {
                    selectedItems: unknown[];
                }) =>
                    h(
                        "button",
                        { "data-custom-delete": "" },
                        `Remove ${selectedItems.length}`,
                    ),
            },
            attachTo: document.body,
        });

        await openDropdown(wrapper, "Actions");
        expect(
            document.body.querySelector("[data-custom-delete]")?.textContent,
        ).toBe("Remove 0");
        expect(document.body.textContent).not.toContain("Delete");
        expect(wrapper.text()).toContain("Edit");
    });

    it("forwards public feature slots through internal components", async () => {
        const resource = topicResource();
        resource.state.filters.status = {
            enabled: true,
            clause: "equals",
            value: "featured",
        };
        const wrapper = mount(DataTable, {
            props: { resource },
            slots: {
                beforeActions: () =>
                    h("span", { "data-before-actions": "" }, "Before"),
                "filter(status)": () =>
                    h("span", { "data-custom-filter": "" }, "Filter"),
                "header(name)": () =>
                    h("span", { "data-custom-header": "" }, "Topic"),
                "cell(name)": ({ item }: { item: { name: string } }) =>
                    h("strong", { "data-custom-cell": "" }, item.name),
            },
            attachTo: document.body,
            global: {
                stubs: {
                    PopoverContent: {
                        template: "<div data-test-popover><slot /></div>",
                    },
                },
            },
        });

        expect(wrapper.get("[data-before-actions]").text()).toBe("Before");
        expect(wrapper.get("[data-custom-header]").text()).toBe("Topic");
        expect(
            wrapper.findAll("[data-custom-cell]").map((cell) => cell.text()),
        ).toEqual(["Alpha", "Beta"]);

        expect(wrapper.get("[data-custom-filter]").text()).toBe("Filter");
    });

    it("renders the column chooser with shadcn dropdown primitives", () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            attachTo: document.body,
        });

        const columnMenu = wrapper
            .findAllComponents(UiDropdownMenu)
            .find(
                (candidate) =>
                    candidate.findComponent(UiDropdownMenuTrigger).text() ===
                    "Columns",
            );
        expect(columnMenu).toBeDefined();
        expect(columnMenu!.findComponent(UiDropdownMenuContent).exists()).toBe(
            true,
        );
        expect(wrapper.find("details").exists()).toBe(false);
    });

    it("renders filters as an add filter menu", async () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            attachTo: document.body,
        });

        await openDropdown(wrapper, "Filters");

        const filter = document.body.querySelector<HTMLElement>(
            '[data-slot="dropdown-menu-item"]',
        );

        expect(filter?.textContent).toContain("Status");
        expect(filter?.querySelector("svg")).not.toBeNull();
    });

    it("offers clearing all filters when a filter is active", async () => {
        const resource = topicResource();
        resource.state.filters.status = {
            enabled: true,
            clause: "equals",
            value: "featured",
        };
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        await openDropdown(wrapper, "Filters");

        expect(document.body.textContent).toContain("Clear all filters");
        expect(
            document.body.querySelector<HTMLElement>(
                '[data-slot="dropdown-menu-item"][data-disabled]',
            )?.textContent,
        ).toContain("Status");
    });

    it("resolves action icons inside the actions menu", async () => {
        const resource = topicResource();
        resource.actions[0] = {
            ...resource.actions[0],
            icon: "Trash",
            labelHidden: true,
            tooltip: "Delete selected topics",
        };
        const TestIcon = () => h("svg", { "data-test-icon": "trash" });
        const iconResolver = vi.fn(() => TestIcon);

        const wrapper = mount(DataTable, {
            props: { resource, iconResolver },
            attachTo: document.body,
        });

        await openDropdown(wrapper, "Actions");
        const action = document.body.querySelector<HTMLElement>(
            '[data-slot="dropdown-menu-item"]',
        );

        expect(action?.getAttribute("title")).toBe("Delete selected topics");
        expect(
            action?.querySelector('[data-test-icon="trash"]'),
        ).not.toBeNull();
        expect(action?.textContent).toBe("Delete");
        expect(iconResolver).toHaveBeenCalledWith("Trash", resource.actions[0]);
    });

    it("renders server-declared column presentation", () => {
        const resource = topicResource();
        resource.columns[0] = {
            ...resource.columns[0],
            type: "badge",
            tooltip: "Public topic name",
            headerClass: "font-semibold",
            cellClass: "max-w-sm",
            wrap: true,
            truncate: 2,
        };
        resource.results.data[0]._table!.cells = {
            name: { variant: "success" },
        };

        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });
        const header = wrapper.get("th.font-semibold");
        const cell = wrapper.get("td.max-w-sm");

        expect(header.attributes("title")).toBe("Public topic name");
        expect(cell.classes()).toContain("tb-cell-wrap");
        expect(cell.classes()).toContain("tb-cell-truncate");
        expect(cell.attributes("style")).toContain("--tb-line-clamp: 2");
        expect(cell.get('.tb-badge[data-style="success"]').text()).toBe(
            "Alpha",
        );
    });

    it("renders server-declared images and their fallback slot", () => {
        const resource = topicResource();
        resource.results.data[0]._table!.cells = {
            name: {
                image: {
                    urls: ["/avatars/one.png", "/avatars/two.png"],
                    overflow: 2,
                    size: "large",
                    position: "start",
                    rounded: true,
                    alt: "Alpha avatar",
                },
            },
        };
        resource.results.data[1]._table!.cells = {
            name: {
                image: {
                    urls: [],
                    overflow: 0,
                    size: "medium",
                    position: "start",
                    rounded: true,
                },
            },
        };

        const wrapper = mount(DataTable, {
            props: { resource },
            slots: {
                "image-fallback(name)": () =>
                    h("span", { "data-image-fallback": "" }, "Fallback"),
            },
            attachTo: document.body,
        });

        expect(wrapper.findAll('img[src="/avatars/one.png"]')).toHaveLength(1);
        expect(wrapper.get(".tb-image-overflow").text()).toBe("+2");
        expect(wrapper.find(".tb-image-rounded").exists()).toBe(true);
        expect(wrapper.get("[data-image-fallback]").text()).toBe("Fallback");
    });

    it("only navigates a row when the server declares a row URL", async () => {
        const { router } = await import("@inertiajs/vue3");
        const visit = vi.mocked(router.visit);
        visit.mockClear();
        const resource = topicResource();
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        await wrapper.findAll("tbody tr")[0].trigger("click");
        expect(visit).toHaveBeenCalledWith(
            "/topics/1",
            expect.objectContaining({ method: "get" }),
        );

        visit.mockClear();
        await wrapper.findAll("tbody tr")[1].trigger("click");
        expect(visit).not.toHaveBeenCalled();
    });
});
