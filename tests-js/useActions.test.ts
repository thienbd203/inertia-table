import { mount } from "@vue/test-utils";
import { defineComponent, h, nextTick, ref } from "vue";
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

    function mountActions(initialResource = topicResource()) {
        const resource = ref(initialResource);
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

    function resourceWithRows(count: number) {
        const resource = topicResource();
        const template = resource.results.data[0];
        resource.results.data = Array.from({ length: count }, (_, index) => ({
            ...template,
            id: index + 1,
            name: `Topic ${index + 1}`,
            _table: {
                ...template._table!,
                key: index + 1,
                url: null,
                columns: {},
                actions: [],
            },
        }));
        resource.results.total = count;
        resource.results.to = count;

        return resource;
    }

    it("selects the entire filtered result set from the header action", () => {
        const { actions, resource } = mountActions();
        actions.toggleItem(resource.value.results.data[0], 0);
        expect(actions.selectedItems.value).toHaveLength(1);
        expect(actions.selectedCount.value).toBe(1);
        expect(actions.allItemsAreSelected.value).toBe(false);

        actions.toggleAll();

        expect(actions.allSelected.value).toBe(true);
        expect(actions.allItemsAreSelected.value).toBe(true);
        expect(actions.selectedItems.value).toHaveLength(2);
        expect(actions.selectedCount.value).toBe(30);
    });

    it("selects a contiguous current-page range with Shift-click", () => {
        const { actions, resource } = mountActions(resourceWithRows(5));
        const rows = resource.value.results.data;

        actions.toggleItem(rows[1], 1);
        actions.toggleItem(rows[4], 4, true);

        expect([...actions.selectedKeys.value]).toEqual([2, 3, 4, 5]);
        expect(actions.selectedCount.value).toBe(4);
    });

    it("updates exclusions when Shift-clicking an all-results range", () => {
        const { actions, resource } = mountActions(resourceWithRows(5));
        const rows = resource.value.results.data;

        actions.toggleAll();
        actions.toggleItem(rows[1], 1);
        actions.toggleItem(rows[4], 4, true);

        expect([...actions.excludedKeys.value]).toEqual([2, 3, 4, 5]);
        expect(actions.selectedCount.value).toBe(1);

        actions.toggleItem(rows[1], 1, true);

        expect(actions.excludedKeys.value.size).toBe(0);
        expect(actions.selectedCount.value).toBe(5);
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

    it("uses the server-serialized model key when the row has no id field", () => {
        const { actions, resource } = mountActions();
        const item = resource.value.results.data[0];
        item._table!.key = "topic-alpha";
        Reflect.deleteProperty(item, "id");

        actions.toggleItem(item, 0);
        actions.performAction(resource.value.actions[0]);

        expect(visit).toHaveBeenCalledWith(
            "/topics/bulk",
            expect.objectContaining({
                method: "delete",
                data: { ids: ["topic-alpha"] },
            }),
        );
    });

    it("keeps all-results selection when the result page changes", async () => {
        const { actions, resource } = mountActions();
        actions.toggleAll();

        resource.value = {
            ...resource.value,
            state: { ...resource.value.state, page: 2 },
            results: {
                ...resource.value.results,
                currentPage: 2,
            },
        };
        await nextTick();

        expect(actions.allSelected.value).toBe(true);
        expect(actions.selectedCount.value).toBe(30);
        expect(actions.selectedItems.value).toHaveLength(2);
    });

    it("excludes unchecked rows from an all-results bulk action", () => {
        const { actions, resource } = mountActions();
        actions.toggleAll();
        actions.toggleItem(resource.value.results.data[0], 0);

        expect(actions.selectionState.value).toBe("indeterminate");
        expect(actions.selectedCount.value).toBe(29);

        actions.performAction(resource.value.actions[0]);

        expect(visit).toHaveBeenCalledWith(
            "/topics/bulk",
            expect.objectContaining({
                method: "delete",
                data: {
                    ids: [],
                    selection: expect.objectContaining({
                        all: true,
                        keys: [],
                        except: [1],
                        table: "topics",
                    }),
                },
            }),
        );
    });

    it("clears all-results selection when the active filters change", async () => {
        const { actions, resource } = mountActions();
        actions.toggleAll();

        resource.value = {
            ...resource.value,
            state: {
                ...resource.value.state,
                filters: {
                    status: {
                        enabled: true,
                        clause: "equals",
                        value: "featured",
                    },
                },
            },
        };
        await nextTick();

        expect(actions.allSelected.value).toBe(false);
        expect(actions.selectedCount.value).toBe(0);
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
        actions.toggleAll();
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
