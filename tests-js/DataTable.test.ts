import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { topicResource } from "./fixtures";

const { listeners } = vi.hoisted(() => ({
    listeners: new Map<string, () => void>(),
}));

vi.mock("@inertiajs/vue3", () => ({
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

describe("DataTable shadcn renderer", () => {
    beforeEach(() => listeners.clear());

    it("renders the bundled shadcn-vue primitives", () => {
        const wrapper = mount(DataTable, {
            props: {
                resource: topicResource(),
                selectable: true,
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
    });
});
