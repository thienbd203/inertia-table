import { flushPromises, mount } from "@vue/test-utils";
import { h } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { TableItem, TableView } from "../resources/js/types";
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
import { vi as vietnameseMessages } from "../resources/js/i18n";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "../resources/js/components/ui/dropdown-menu";
import AddFilterMenu from "../resources/js/components/table/filters/AddFilterMenu.vue";

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

function attachViews(resource: ReturnType<typeof topicResource>) {
    const view: TableView = {
        id: 7,
        name: "Featured view",
        state: {
            schemaVersion: 1,
            sort: resource.state.sort,
            filters: resource.state.filters,
            columns: resource.state.columns,
            pinnedColumns: { left: [], right: [] },
            perPage: resource.state.perPage,
        },
        isDefault: true,
        isShared: false,
        version: 0,
        canUpdate: true,
        canDelete: true,
        canShare: true,
        canDefault: true,
        endpoints: {
            update: "/views/7?signature=update",
            delete: "/views/7?signature=delete",
            default: "/views/7/default?signature=default",
            share: "/views/7/share?signature=share",
        },
    };
    resource.state.view = 7;
    resource.views = {
        items: [view],
        selected: 7,
        includeSearch: false,
        canCreate: true,
        storeEndpoint: "/views?signature=store",
    };

    return view;
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
        expect(wrapper.find('[data-slot="native-select"]').exists()).toBe(true);
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

    it("renders the saved-view switcher and destructive delete confirmation", async () => {
        const resource = topicResource();
        attachViews(resource);
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        await openDropdown(wrapper, "Featured view");
        expect(document.body.textContent).toContain("Saved views");
        expect(document.body.textContent).toContain("Save view");
        expect(document.body.textContent).toContain("Rename view");

        const deleteItem = Array.from(
            document.body.querySelectorAll<HTMLElement>(
                '[data-slot="dropdown-menu-item"]',
            ),
        ).find((item) => item.textContent?.includes("Delete view"));
        deleteItem?.click();
        await flushPromises();

        expect(document.body.textContent).toContain(
            'Delete the saved view "Featured view"?',
        );
    });

    it("shows a dirty view indicator and exposes reset/update actions", async () => {
        const resource = topicResource();
        attachViews(resource);
        resource.state.sort = "-name";
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        expect(
            wrapper.find('[aria-label="View has unsaved changes"]').exists(),
        ).toBe(true);

        await openDropdown(wrapper, "Featured view");
        expect(document.body.textContent).toContain("Update view");
        expect(document.body.textContent).toContain("Reset changes");
    });

    it("forwards a custom row key resolver to selection and rendering", () => {
        const resource = topicResource();
        const rowKey = vi.fn((item: TableItem) => `topic-${item.id}`);

        mount(DataTable, {
            props: { resource, rowKey },
            attachTo: document.body,
        });

        expect(rowKey).toHaveBeenCalledWith(resource.results.data[0], 0);
        expect(rowKey).toHaveBeenCalledWith(resource.results.data[1], 1);
    });

    it("selects every matching result directly from the header checkbox", async () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            attachTo: document.body,
        });

        await wrapper
            .get('[aria-label="Select all 30 matching results"]')
            .trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain("30 rows selected");
        expect(
            wrapper
                .findAll('tbody [data-slot="checkbox"]')
                .every(
                    (checkbox) =>
                        checkbox.attributes("data-state") === "checked",
                ),
        ).toBe(true);
    });

    it("selects the range from the previous row on Shift-click", async () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            attachTo: document.body,
        });
        const checkboxes = wrapper.findAll('tbody [data-slot="checkbox"]');

        await checkboxes[0].trigger("click");
        await checkboxes[1].trigger("click", { shiftKey: true });
        await flushPromises();

        expect(
            wrapper
                .findAll('tbody [data-slot="checkbox"]')
                .every(
                    (checkbox) =>
                        checkbox.attributes("data-state") === "checked",
                ),
        ).toBe(true);
        expect(wrapper.text()).toContain("2 rows selected");
    });

    it("disables unselectable rows and skips them in a Shift-click range", async () => {
        const resource = topicResource();
        resource.results.data[1]._table!.selectable = false;
        resource.results.selectableTotal = 29;
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });
        const checkboxes = wrapper.findAll('tbody [data-slot="checkbox"]');

        expect(checkboxes[1].attributes("disabled")).toBeDefined();

        await checkboxes[0].trigger("click");
        await checkboxes[1].trigger("click", { shiftKey: true });
        await flushPromises();

        expect(checkboxes[0].attributes("data-state")).toBe("checked");
        expect(checkboxes[1].attributes("data-state")).toBe("unchecked");
        expect(wrapper.text()).toContain("1 row selected");
    });

    it("renders a dash for the partial header selection state", async () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            attachTo: document.body,
        });

        await wrapper
            .findAll('tbody [data-slot="checkbox"]')[0]
            .trigger("click");
        await flushPromises();

        const headerCheckbox = wrapper.get(
            '[aria-label="Select all 30 matching results"]',
        );
        expect(headerCheckbox.attributes("data-state")).toBe("indeterminate");
        expect(
            headerCheckbox
                .find('[data-slot="checkbox-indeterminate-icon"]')
                .exists(),
        ).toBe(true);
        expect(
            headerCheckbox.find('[data-slot="checkbox-checked-icon"]').exists(),
        ).toBe(false);
    });

    it("renders built-in interface text in Vietnamese", async () => {
        const wrapper = mount(DataTable, {
            props: {
                resource: topicResource(),
                locale: "vi-VN",
                messages: vietnameseMessages,
            },
            attachTo: document.body,
        });

        expect(
            wrapper.get('input[type="search"]').attributes("placeholder"),
        ).toBe("Tìm kiếm…");
        expect(wrapper.text()).toContain("Thao tác");
        expect(wrapper.text()).toContain("Bộ lọc");
        expect(wrapper.text()).toContain("Cột");
        expect(wrapper.text()).toContain("Số dòng mỗi trang");

        await openDropdown(wrapper, "Thao tác");
        expect(document.body.textContent).toContain("Thao tác hàng loạt");
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

    it("renders accessible row actions in an action-column dropdown", async () => {
        const resource = topicResource();
        resource.columns[2].asDropdown = true;
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        const trigger = wrapper.get('[aria-label="Row actions"]');
        expect(trigger.attributes("aria-haspopup")).toBe("menu");

        await trigger.trigger("keydown", { key: "Enter" });
        await flushPromises();

        const menuItem =
            document.body.querySelector<HTMLElement>('[role="menuitem"]');
        expect(menuItem?.textContent).toContain("Edit");
    });

    it("preserves custom action slots inside a row-action dropdown", async () => {
        const resource = topicResource();
        resource.columns[2].asDropdown = true;
        const wrapper = mount(DataTable, {
            props: { resource },
            slots: {
                "action(edit)": ({ item }: { item: { name: string } }) =>
                    h(
                        "button",
                        { "data-custom-edit": "" },
                        `Edit ${item.name}`,
                    ),
            },
            attachTo: document.body,
        });

        await wrapper.get('[aria-label="Row actions"]').trigger("click");
        await flushPromises();

        expect(
            document.body.querySelector("[data-custom-edit]")?.textContent,
        ).toBe("Edit Alpha");
    });

    it("forwards public feature slots through internal components", async () => {
        const resource = topicResource();
        let filterSlotProps: Record<string, unknown> = {};
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
                "filter(status)": (props: Record<string, unknown>) => {
                    filterSlotProps = props;

                    return h("span", { "data-custom-filter": "" }, "Filter");
                },
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
        expect(filterSlotProps.filter).toEqual(resource.filters[0]);
        expect(filterSlotProps.state).toEqual(resource.state.filters.status);
        expect(filterSlotProps.value).toBe("featured");
        expect(filterSlotProps.update).toBeTypeOf("function");
        expect(filterSlotProps.close).toBeTypeOf("function");
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

    it("opens a filter editor after adding a filter", async () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            attachTo: document.body,
        });

        wrapper.findComponent(AddFilterMenu).vm.$emit("add", "status");
        await flushPromises();

        expect(
            document.body.querySelector('[data-slot="popover-content"]'),
        ).not.toBeNull();
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
