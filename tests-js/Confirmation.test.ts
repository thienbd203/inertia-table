import { nextTick } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { TableAction } from "../resources/js/types";

const { visit, listeners } = vi.hoisted(() => ({
    visit: vi.fn(),
    listeners: new Map<string, () => void>(),
}));

vi.mock("@inertiajs/vue3", () => ({
    router: {
        visit,
        on: vi.fn((event: string, callback: () => void) => {
            listeners.set(event, callback);
            return vi.fn();
        }),
    },
    usePage: () => ({ url: "/admin/topics" }),
}));

import Confirmation from "../resources/js/components/table/actions/Confirmation.vue";
import { mountWithTableContext } from "./harness";

const deleteAction: TableAction = {
    key: "delete",
    label: "Delete",
    scope: "bulk",
    authorized: true,
    disabled: false,
    hidden: false,
    variant: "destructive",
    icon: null,
    labelHidden: false,
    tooltip: null,
    buttonClass: null,
    disabledTooltip: null,
    confirmation: {
        title: "Delete topics",
        message: "This action cannot be undone.",
        confirmLabel: "Yes",
        cancelLabel: "Cancel",
    },
    endpoint: { method: "delete", url: "/topics/bulk" },
    meta: {},
};

describe("Confirmation", () => {
    beforeEach(() => {
        visit.mockReset();
        listeners.clear();
    });

    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("shows the declared confirmation title, message, and button labels", async () => {
        const { actions } = mountWithTableContext(Confirmation);
        actions.toggleAll();
        actions.performAction(deleteAction);
        await nextTick();

        expect(document.body.textContent).toContain("Delete topics");
        expect(document.body.textContent).toContain(
            "This action cannot be undone.",
        );
        expect(document.body.textContent).toContain("Cancel");
        expect(document.body.textContent).toContain("Yes");
        expect(visit).not.toHaveBeenCalled();
    });

    it("clears the pending action without visiting when cancelled", async () => {
        const { actions } = mountWithTableContext(Confirmation);
        actions.toggleAll();
        actions.performAction(deleteAction);
        await nextTick();

        const cancel = Array.from(
            document.body.querySelectorAll('[data-slot="button"]'),
        ).find((button) => button.textContent?.trim() === "Cancel");
        (cancel as HTMLElement).click();
        await nextTick();

        expect(actions.pendingAction.value).toBeNull();
        expect(visit).not.toHaveBeenCalled();
    });

    it("performs the endpoint action when confirmed", async () => {
        const { actions } = mountWithTableContext(Confirmation);
        actions.toggleAll();
        actions.performAction(deleteAction);
        await nextTick();

        const confirm = Array.from(
            document.body.querySelectorAll('[data-slot="button"]'),
        ).find((button) => button.textContent?.trim() === "Yes");
        (confirm as HTMLElement).click();
        await nextTick();

        expect(actions.pendingAction.value).not.toBeNull();
        expect(actions.isPerformingAction.value).toBe(true);
        expect(
            Array.from(
                document.body.querySelectorAll('[data-slot="button"]'),
            ).every((button) => (button as HTMLButtonElement).disabled),
        ).toBe(true);
        expect(document.body.querySelector(".animate-spin")).not.toBeNull();
        expect(visit).toHaveBeenCalledOnce();
        expect(visit.mock.calls[0][0]).toBe("/topics/bulk");
        expect(visit.mock.calls[0][1]).toMatchObject({ method: "delete" });
    });
});
