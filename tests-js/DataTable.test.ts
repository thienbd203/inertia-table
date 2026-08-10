import { mount } from "@vue/test-utils";
import { h } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
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

describe("DataTable shadcn renderer", () => {
    beforeEach(() => {
        listeners.clear();
        setIconResolver(null);
    });

    it("renders the bundled shadcn-vue primitives", () => {
        const wrapper = mount(DataTable, {
            props: {
                resource: topicResource(),
            },
            global: {
                stubs: { Teleport: true },
            },
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
        expect(wrapper.text()).toContain("Delete");
        expect(wrapper.text()).toContain("Columns");
    });

    it("allows one action renderer to be replaced by its dynamic slot", () => {
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
            global: { stubs: { Teleport: true } },
        });

        expect(wrapper.get("[data-custom-delete]").text()).toBe("Remove 0");
        expect(wrapper.text()).not.toContain("Delete");
        expect(wrapper.text()).toContain("Edit");
    });

    it("forwards public feature slots through internal components", () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
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
            global: { stubs: { Teleport: true } },
        });

        expect(wrapper.get("[data-before-actions]").text()).toBe("Before");
        expect(wrapper.get("[data-custom-filter]").text()).toBe("Filter");
        expect(wrapper.get("[data-custom-header]").text()).toBe("Topic");
        expect(
            wrapper.findAll("[data-custom-cell]").map((cell) => cell.text()),
        ).toEqual(["Alpha", "Beta"]);
    });

    it("renders the column chooser with shadcn dropdown primitives", () => {
        const wrapper = mount(DataTable, {
            props: { resource: topicResource() },
            global: { stubs: { Teleport: true } },
        });

        expect(wrapper.findComponent(UiDropdownMenu).exists()).toBe(true);
        expect(wrapper.findComponent(UiDropdownMenuTrigger).text()).toBe(
            "Columns",
        );
        expect(wrapper.findComponent(UiDropdownMenuContent).exists()).toBe(
            true,
        );
        expect(wrapper.find("details").exists()).toBe(false);
    });

    it("resolves action icons and supports icon-only buttons", () => {
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
            global: { stubs: { Teleport: true } },
        });

        const action = wrapper.get('[data-slot="button"][aria-label="Delete"]');

        expect(action.attributes("title")).toBe("Delete selected topics");
        expect(action.find('[data-test-icon="trash"]').exists()).toBe(true);
        expect(action.text()).toBe("");
        expect(iconResolver).toHaveBeenCalledWith("Trash", resource.actions[0]);
    });
});
