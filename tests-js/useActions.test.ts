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
        resource.results.selectableTotal = count;
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

    it("uses the selectable total instead of the raw result total", () => {
        const resource = topicResource();
        resource.results.selectableTotal = 18;
        const { actions } = mountActions(resource);

        actions.toggleAll();

        expect(actions.selectedCount.value).toBe(18);
        expect(actions.allItemsAreSelected.value).toBe(true);
    });

    it("selects a contiguous current-page range with Shift-click", () => {
        const { actions, resource } = mountActions(resourceWithRows(5));
        const rows = resource.value.results.data;

        actions.toggleItem(rows[1], 1);
        actions.toggleItem(rows[4], 4, true);

        expect([...actions.selectedKeys.value]).toEqual([2, 3, 4, 5]);
        expect(actions.selectedCount.value).toBe(4);
    });

    it("skips unselectable rows in a Shift-click range", () => {
        const { actions, resource } = mountActions(resourceWithRows(5));
        const rows = resource.value.results.data;
        rows[2]._table!.selectable = false;
        resource.value.results.selectableTotal = 4;

        actions.toggleItem(rows[0], 0);
        actions.toggleItem(rows[4], 4, true);

        expect([...actions.selectedKeys.value]).toEqual([1, 2, 4, 5]);
        expect(actions.selectedCount.value).toBe(4);
        expect(actions.isItemSelected(rows[2], 2)).toBe(false);
    });

    it("resolves a partial header state to all matching results", () => {
        const { actions, resource } = mountActions();

        actions.toggleItem(resource.value.results.data[0], 0);
        expect(actions.selectionState.value).toBe("indeterminate");

        actions.toggleAll(false);

        expect(actions.allSelected.value).toBe(true);
        expect(actions.selectedCount.value).toBe(30);
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

    it("resolves row attributes and count in confirmation placeholders", () => {
        const { actions, resource } = mountActions();
        const item = resource.value.results.data[0];
        const action = {
            ...item._table!.actions[0],
            confirmation: {
                title: "Edit :name",
                message: "Update :count topic named :name?",
                confirmLabel: "Update :name",
                cancelLabel: "Cancel",
            },
        };

        actions.performAction(action, item);

        expect(actions.pendingConfirmation.value).toEqual({
            title: "Edit Alpha",
            message: "Update 1 topic named Alpha?",
            confirmLabel: "Update Alpha",
            cancelLabel: "Cancel",
        });
    });

    it("resolves singular plural and all-matching confirmation variants", () => {
        const resource = resourceWithRows(5);
        const action = {
            ...resource.actions[0],
            confirmation: {
                title: [
                    "Delete :count topic?",
                    "Delete :count topics?",
                    "Delete all :count matching topics?",
                ] as [string, string, string],
                message: [
                    "One topic",
                    ":count topics",
                    "All :count matching topics",
                ] as [string, string, string],
                confirmLabel: "Delete :count",
                cancelLabel: "Cancel",
            },
        };
        const { actions } = mountActions(resource);

        actions.toggleItem(resource.results.data[0], 0);
        actions.performAction(action);
        expect(actions.pendingConfirmation.value?.title).toBe(
            "Delete 1 topic?",
        );
        actions.cancelAction();

        actions.toggleItem(resource.results.data[1], 1);
        actions.performAction(action);
        expect(actions.pendingConfirmation.value?.title).toBe(
            "Delete 2 topics?",
        );
        actions.cancelAction();

        actions.toggleAll();
        actions.performAction(action);
        expect(actions.pendingConfirmation.value?.title).toBe(
            "Delete all 5 matching topics?",
        );
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
