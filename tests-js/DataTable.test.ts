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
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "../resources/js/components/ui/dropdown-menu";

describe("DataTable shadcn renderer", () => {
    beforeEach(() => listeners.clear());

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
});
