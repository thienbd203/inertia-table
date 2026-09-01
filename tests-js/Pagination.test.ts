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
import { topicResource } from "./fixtures";
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
                selectableTotal: 30,
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
                selectableTotal: 4,
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
                selectableTotal: 60,
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

    it("renders simple pagination without first, last, or total-page controls", async () => {
        const base = topicResource();
        const { wrapper } = mountWithTableContext(Pagination, {
            options: { ...base.options, paginationType: "simple" },
            results: {
                data: [],
                currentPage: 2,
                from: 2,
                lastPage: null,
                links: [],
                perPage: 1,
                to: 2,
                total: null,
                selectableTotal: 3,
                hasPreviousPage: true,
                hasNextPage: true,
            },
        });

        expect(wrapper.text()).toContain("Page 2");
        expect(wrapper.find('[aria-label="First page"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Last page"]').exists()).toBe(false);

        await wrapper.get('[aria-label="Next page"]').trigger("click");

        expect(visit.mock.calls[0][0]).toContain(
            "table%5Btopics%5D%5Bpage%5D=3",
        );
    });

    it("navigates cursor pagination with opaque tokens", async () => {
        const base = topicResource();
        const { wrapper } = mountWithTableContext(Pagination, {
            options: { ...base.options, paginationType: "cursor" },
            state: { ...base.state, cursor: null },
            results: {
                data: [],
                currentPage: null,
                from: null,
                lastPage: null,
                links: [],
                perPage: 25,
                to: null,
                total: null,
                selectableTotal: 30,
                hasPreviousPage: false,
                hasNextPage: true,
                previousCursor: null,
                nextCursor: "eyJpZCI6MjV9",
            },
        });

        expect(wrapper.text()).not.toContain("Page ");
        expect(wrapper.find('[aria-label="First page"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label="Last page"]').exists()).toBe(false);

        await wrapper.get('[aria-label="Next page"]').trigger("click");

        const url = new URL(visit.mock.calls[0][0], "http://toolbelt.local");
        expect(url.searchParams.get("table[topics][cursor]")).toBe(
            "eyJpZCI6MjV9",
        );
        expect(url.searchParams.has("table[topics][page]")).toBe(false);
    });
});
