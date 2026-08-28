import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

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

import Pagination from "../resources/js/components/table/layout/Pagination.vue";
import { mountWithTableContext } from "./harness";

describe("Pagination", () => {
    beforeEach(() => {
        visit.mockReset();
        listeners.clear();
    });

    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("disables the first and previous buttons on the first page", () => {
        const { wrapper } = mountWithTableContext(Pagination, {
            results: {
                data: [],
                currentPage: 1,
                from: 1,
                lastPage: 2,
                links: [],
                perPage: 25,
                to: 2,
                total: 30,
            },
        });

        const first = wrapper.get('[aria-label="First page"]');
        const previous = wrapper.get('[aria-label="Previous page"]');
        const next = wrapper.get('[aria-label="Next page"]');
        const last = wrapper.get('[aria-label="Last page"]');

        expect(first.attributes("disabled")).toBeDefined();
        expect(previous.attributes("disabled")).toBeDefined();
        expect(next.attributes("disabled")).toBeUndefined();
        expect(last.attributes("disabled")).toBeUndefined();
    });

    it("disables the next and last buttons on the last page", () => {
        const { wrapper } = mountWithTableContext(Pagination, {
            results: {
                data: [],
                currentPage: 2,
                from: 3,
                lastPage: 2,
                links: [],
                perPage: 25,
                to: 4,
                total: 4,
            },
        });

        expect(
            wrapper.get('[aria-label="Next page"]').attributes("disabled"),
        ).toBeDefined();
        expect(
            wrapper.get('[aria-label="Last page"]').attributes("disabled"),
        ).toBeDefined();
        expect(
            wrapper.get('[aria-label="First page"]').attributes("disabled"),
        ).toBeUndefined();
    });

    it("visits the next page when the next button is clicked", async () => {
        const { wrapper } = mountWithTableContext(Pagination, {
            results: {
                data: [],
                currentPage: 1,
                from: 1,
                lastPage: 3,
                links: [],
                perPage: 25,
                to: 25,
                total: 60,
            },
        });

        await wrapper.get('[aria-label="Next page"]').trigger("click");

        expect(visit).toHaveBeenCalledOnce();
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bpage%5D=2",
        );
    });

    it("changes the page size through the rows-per-page select", async () => {
        const { wrapper } = mountWithTableContext(Pagination);

        await wrapper.get("select").setValue("10");

        expect(visit).toHaveBeenCalledOnce();
        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5BperPage%5D=10",
        );
    });

    it("shows the total matching selection count across all pages", async () => {
        const { actions, wrapper } = mountWithTableContext(Pagination);

        actions.toggleAll();
        await Promise.resolve();

        expect(wrapper.text()).toContain("30 rows selected");
    });
});
