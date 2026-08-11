import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    router: { visit, on: vi.fn(() => vi.fn()) },
    usePage: () => ({ url: "/admin/topics" }),
}));

import { useActions } from "../resources/js/useActions";
import { useTable } from "../resources/js/useTable";

describe("useActions", () => {
    beforeEach(() => visit.mockReset());

    function mountActions() {
        const resource = ref(topicResource());
        let actions: ReturnType<typeof useActions<Topic>>;
        const wrapper = mount(
            defineComponent({
                setup() {
                    const table = useTable(resource);
                    actions = useActions(table);
                    return () => h("div");
                },
            }),
        );
        return { actions: actions!, resource, wrapper };
    }

    it("owns selection independently from table state", () => {
        const { actions, resource } = mountActions();
        actions.toggleItem(resource.value.results.data[0], 0);
        expect(actions.selectedItems.value).toHaveLength(1);
        expect(actions.allItemsAreSelected.value).toBe(false);
        actions.toggleAll();
        expect(actions.selectedItems.value).toHaveLength(2);
    });

    it("performs a declared bulk action with selected keys", () => {
        const { actions, resource } = mountActions();
        actions.toggleItem(resource.value.results.data[0], 0);
        actions.performAction(resource.value.actions[0]);

        expect(visit).toHaveBeenCalledWith(
            "/topics/bulk",
            expect.objectContaining({ method: "delete", data: { ids: [1] } }),
        );
    });

    it("waits for confirmation when the action requires it", () => {
        const { actions, resource } = mountActions();
        const action = {
            ...resource.value.actions[0],
            confirmation: {
                title: "Delete",
                message: "Sure?",
                confirmLabel: "Delete",
                cancelLabel: "Cancel",
            },
        };
        actions.performAction(action);
        expect(visit).not.toHaveBeenCalled();
        expect(actions.pendingAction.value?.action.key).toBe("delete");
        actions.confirmAction();
        expect(visit).toHaveBeenCalledOnce();
    });

    it("performs resolved row link actions without duplicating the id in the query", () => {
        const { actions, resource } = mountActions();
        const item = resource.value.results.data[0];
        actions.performAction(item._table!.actions[0], item);

        expect(visit).toHaveBeenCalledWith(
            "/topics/1",
            expect.objectContaining({ method: "get", data: {} }),
        );
    });
});
