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
import ExportsMenu from "../resources/js/components/table/exports/ExportsMenu.vue";
import { mountWithTableContext } from "./harness";

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

    it("renders exports and keeps selection available for selected exports without bulk actions", async () => {
        const resource = topicResource();
        resource.actions = [];
        resource.capabilities.hasBulkActions = false;
        resource.exports = [
            {
                key: "selected",
                label: "Selected CSV",
                filename: "topics.csv",
                type: "csv",
                scope: "selected",
                requiresSelection: true,
                endpoint: "/exports/selected?signature=valid",
                meta: {},
            },
        ];
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        expect(wrapper.find('thead [data-slot="checkbox"]').exists()).toBe(
            true,
        );
        await openDropdown(wrapper, "Export");
        expect(document.body.textContent).toContain("Selected CSV");
        const exportItem = Array.from(
            document.body.querySelectorAll<HTMLElement>(
                '[data-slot="dropdown-menu-item"]',
            ),
        ).find((item) => item.textContent?.includes("Selected CSV"));
        expect(exportItem?.getAttribute("data-disabled")).not.toBeNull();
    });

    it("renders a completed queued export as a named download", async () => {
        const { exports } = mountWithTableContext(ExportsMenu, {
            exports: [
                {
                    key: "queued",
                    label: "All rows",
                    filename: "topics.csv",
                    type: "csv",
                    scope: "all",
                    requiresSelection: false,
                    queued: true,
                    endpoint: "/exports/queued?signature=valid",
                    meta: {},
                },
            ],
        });

        exports.updateQueuedExport({
            id: "export-1",
            status: "ready",
            filename: "topics.csv",
            url: "/downloads/export-1",
        });
        await flushPromises();

        const download = document.body.querySelector<HTMLAnchorElement>(
            'a[href="/downloads/export-1"]',
        );
        expect(download?.getAttribute("download")).toBe("topics.csv");
    });

    it("keeps tracking a dismissed queued export and notifies when it is ready", async () => {
        const { exports } = mountWithTableContext(ExportsMenu, {
            exports: [
                {
                    key: "queued",
                    label: "All rows",
                    filename: "topics.csv",
                    type: "csv",
                    scope: "all",
                    requiresSelection: false,
                    queued: true,
                    endpoint: "/exports/queued?signature=valid",
                    meta: {},
                },
            ],
        });

        exports.updateQueuedExport({
            id: "export-2",
            status: "processing",
            filename: "topics.csv",
            url: null,
        });
        await flushPromises();

        expect(document.body.textContent).toContain(
            "Your export is being processed",
        );
        expect(document.body.textContent).toContain(
            "You can close this dialog and we'll notify you once it's done.",
        );

        Array.from(
            document.body.querySelectorAll<HTMLButtonElement>(
                '[data-slot="button"]',
            ),
        )
            .find((button) => button.textContent?.trim() === "Close")
            ?.click();
        await flushPromises();

        expect(exports.queuedExport.value?.status).toBe("processing");
        expect(document.body.textContent).not.toContain(
            "Your export is being processed",
        );

        exports.updateQueuedExport({
            id: "export-2",
            status: "ready",
            filename: "topics.csv",
            url: "/downloads/export-2",
        });
        await flushPromises();

        expect(document.body.textContent).toContain("Export ready");
        expect(
            document.body.querySelector<HTMLAnchorElement>(
                'a[href="/downloads/export-2"]',
            ),
        ).not.toBeNull();
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
        resource.columns[2].stickable = true;
        resource.columns[2].sticky = true;
        resource.state.pinnedColumns = {
            left: [],
            right: ["__actions"],
        };
        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        const trigger = wrapper.get('[aria-label="Row actions"]');
        expect(
            trigger.element.closest("td")?.getAttribute("data-sticky-side"),
        ).toBe("right");
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

    it("renders a server-defined genuine empty state with actions and data attributes", () => {
        const resource = topicResource();
        resource.results = {
            ...resource.results,
            data: [],
            from: null,
            to: null,
            total: 0,
            selectableTotal: 0,
        };
        resource.capabilities.hasEmptyState = true;
        resource.emptyState = {
            title: "No topics yet",
            message: "Create the first topic.",
            icon: false,
            actions: [
                {
                    label: "Create topic",
                    url: {
                        url: "/topics/create",
                        preserveScroll: true,
                        preserveState: true,
                        newTab: false,
                        download: false,
                        disabled: false,
                    },
                    variant: "info",
                    icon: null,
                    buttonClass: "create-topic",
                    dataAttributes: { "data-intent": "create" },
                    meta: { source: "empty-state" },
                },
            ],
            dataAttributes: { "data-kind": "topics" },
            meta: { surface: "admin" },
        };

        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        const emptyState = wrapper.get('.tb-empty-state[data-kind="topics"]');
        const action = emptyState.get('a[data-intent="create"]');
        expect(emptyState.text()).toContain("No topics yet");
        expect(emptyState.text()).toContain("Create the first topic.");
        expect(action.attributes("href")).toBe("/topics/create");
        expect(action.classes()).toContain("create-topic");
        expect(action.attributes("data-empty-state-variant")).toBe("info");
    });

    it("forwards normalized server row data attributes to the table row", () => {
        const resource = topicResource();
        resource.results.data[0]._table!.dataAttributes = {
            "data-record-id": 1,
            "data-status": "PUBLISHED",
        };

        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });
        const row = wrapper.get('tbody tr[data-record-id="1"]');

        expect(row.attributes("data-status")).toBe("PUBLISHED");
    });

    it("does not render pagination controls for an unpaginated resource", () => {
        const resource = topicResource();
        resource.capabilities.paginated = false;

        const wrapper = mount(DataTable, {
            props: { resource },
            attachTo: document.body,
        });

        expect(wrapper.find('[aria-label="Next page"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain("Rows per page");
    });

    it("renders sticky headers and measured column offsets with logical RTL-safe properties", async () => {
        const originalRect = HTMLElement.prototype.getBoundingClientRect;
        let nameWidth = 120;
        HTMLElement.prototype.getBoundingClientRect = function () {
            const width = this.classList.contains("tb-selection-cell")
                ? 40
                : this.getAttribute("data-column") === "name"
                  ? nameWidth
                  : 80;

            return {
                x: 0,
                y: 0,
                top: 0,
                right: width,
                bottom: 40,
                left: 0,
                width,
                height: 40,
                toJSON: () => ({}),
            };
        };

        try {
            const resource = topicResource();
            resource.options.stickyHeader = true;
            resource.columns[0].stickable = true;
            resource.columns[1].stickable = true;
            resource.columns[2].stickable = true;
            resource.state.pinnedColumns = {
                left: ["name", "is_featured"],
                right: ["__actions"],
            };
            const wrapper = mount(DataTable, {
                props: { resource },
                attachTo: document.body,
            });

            await flushPromises();

            const selection = wrapper.get("thead .tb-selection-cell");
            const name = wrapper.get('thead th[data-column="name"]');
            const featured = wrapper.get('thead th[data-column="is_featured"]');
            const actions = wrapper.get('thead th[data-column="__actions"]');

            expect(
                wrapper.get('[data-slot="table-container"]').classes(),
            ).toContain("tb-sticky-header-container");
            expect(selection.classes()).toContain("tb-sticky-header-cell");
            expect(name.attributes("style")).toContain(
                "inset-inline-start: 40px",
            );
            expect(featured.attributes("style")).toContain(
                "inset-inline-start: 160px",
            );
            expect(featured.attributes("data-sticky-edge")).toBe("start");
            expect(actions.attributes("style")).toContain(
                "inset-inline-end: 0px",
            );
            expect(actions.attributes("data-sticky-edge")).toBe("end");

            const container = wrapper.get('[data-slot="table-container"]');
            Object.defineProperties(container.element, {
                scrollWidth: { configurable: true, value: 400 },
                clientWidth: { configurable: true, value: 200 },
            });
            container.element.scrollLeft = 0;
            await container.trigger("scroll");
            expect(
                container.attributes("data-scrolled-from-start"),
            ).toBeUndefined();
            expect(container.attributes("data-scrolled-from-end")).toBe("");

            container.element.scrollLeft = 80;
            await container.trigger("scroll");
            expect(container.attributes("data-scrolled-from-start")).toBe("");
            expect(container.attributes("data-scrolled-from-end")).toBe("");

            container.element.scrollLeft = 200;
            await container.trigger("scroll");
            expect(container.attributes("data-scrolled-from-start")).toBe("");
            expect(
                container.attributes("data-scrolled-from-end"),
            ).toBeUndefined();

            nameWidth = 200;
            window.dispatchEvent(new Event("resize"));
            await flushPromises();
            expect(
                wrapper
                    .get('thead th[data-column="is_featured"]')
                    .attributes("style"),
            ).toContain("inset-inline-start: 240px");

            await wrapper.setProps({
                resource: {
                    ...resource,
                    state: {
                        ...resource.state,
                        columns: {
                            ...resource.state.columns,
                            name: false,
                        },
                    },
                },
            });
            await flushPromises();
            expect(
                wrapper
                    .get('thead th[data-column="is_featured"]')
                    .attributes("style"),
            ).toContain("inset-inline-start: 40px");
        } finally {
            HTMLElement.prototype.getBoundingClientRect = originalRect;
        }
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
