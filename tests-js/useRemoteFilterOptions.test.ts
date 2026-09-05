import { flushPromises } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { TableFilter } from "../resources/js/types";
import {
    clearRemoteFilterOptionsCache,
    useRemoteFilterOptions,
} from "../resources/js/components/table/filters/useRemoteFilterOptions";
import { mountWithTableContext } from "./harness";

vi.mock("@inertiajs/vue3", () => ({
    router: { visit: vi.fn(), on: vi.fn(() => vi.fn()) },
    usePage: () => ({ url: "/admin/topics" }),
}));

function remoteFilter(endpoint = "/filter-options/categories"): TableFilter {
    return {
        attribute: "category_id",
        label: "Category",
        type: "set",
        clauses: ["equals"],
        options: [{ value: 99, label: "Restored category" }],
        remote: {
            endpoint,
            searchable: true,
            dependsOn: ["status"],
            perPage: 2,
            debounceTime: 100,
            cacheTtl: 30_000,
            maxCacheEntries: 10,
            withCounts: true,
        },
        meta: {},
    };
}

function jsonResponse(payload: unknown, status = 200): Response {
    return new Response(JSON.stringify(payload), {
        status,
        headers: { "Content-Type": "application/json" },
    });
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((next) => {
        resolve = next;
    });

    return { promise, resolve };
}

describe("remote filter options", () => {
    beforeEach(() => {
        clearRemoteFilterOptionsCache();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
        document.body.innerHTML = "";
    });

    it("keeps hydrated labels and appends cursor pages with counts", async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({
                    options: [
                        { value: 1, label: "Books", count: 12 },
                        { value: 2, label: "Games", count: 4 },
                    ],
                    selected: [
                        { value: 99, label: "Restored category", count: 1 },
                    ],
                    nextCursor: "opaque-next",
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    options: [{ value: 3, label: "Music", count: 8 }],
                    selected: [],
                    nextCursor: null,
                }),
            );
        vi.stubGlobal("fetch", fetchMock);
        const filter = ref(remoteFilter());
        const modelValue = ref<unknown>(99);
        let remote!: ReturnType<typeof useRemoteFilterOptions>;
        const Probe = defineComponent({
            setup() {
                remote = useRemoteFilterOptions(filter, modelValue);

                return () =>
                    h(
                        "div",
                        remote.options.value.map(
                            (option) =>
                                `${option.label}:${option.count ?? "-"}`,
                        ),
                    );
            },
        });
        const mounted = mountWithTableContext(Probe, {
            filters: [filter.value],
            state: {
                search: "",
                sort: "name",
                filters: {
                    status: {
                        enabled: true,
                        clause: "equals",
                        value: "active",
                    },
                    category_id: {
                        enabled: true,
                        clause: "equals",
                        value: 99,
                    },
                },
                columns: {},
                page: 1,
                perPage: 25,
            },
        });

        await flushPromises();
        expect(mounted.wrapper.text()).toContain("Restored category:1");
        expect(mounted.wrapper.text()).toContain("Books:12");
        expect(remote.nextCursor.value).toBe("opaque-next");

        await remote.loadMore();
        expect(mounted.wrapper.text()).toContain("Music:8");
        expect(remote.nextCursor.value).toBeNull();
        expect(
            JSON.parse(String(fetchMock.mock.calls[1][1]?.body)),
        ).toMatchObject({ cursor: "opaque-next", selected: [99] });
    });

    it("cancels stale searches and only applies the latest response", async () => {
        vi.useFakeTimers();
        const initial = deferred<Response>();
        const oldSearch = deferred<Response>();
        const newSearch = deferred<Response>();
        const fetchMock = vi
            .fn()
            .mockReturnValueOnce(initial.promise)
            .mockReturnValueOnce(oldSearch.promise)
            .mockReturnValueOnce(newSearch.promise);
        vi.stubGlobal("fetch", fetchMock);
        const filter = ref(remoteFilter());
        let remote!: ReturnType<typeof useRemoteFilterOptions>;
        const Probe = defineComponent({
            setup() {
                remote = useRemoteFilterOptions(filter, ref(null));
                return () =>
                    h(
                        "div",
                        remote.options.value.map((option) => option.label),
                    );
            },
        });
        const mounted = mountWithTableContext(Probe, {
            filters: [filter.value],
        });

        initial.resolve(
            jsonResponse({ options: [], selected: [], nextCursor: null }),
        );
        await flushPromises();

        remote.search.value = "old";
        await vi.advanceTimersByTimeAsync(100);
        remote.search.value = "new";
        await vi.advanceTimersByTimeAsync(100);

        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(
            (fetchMock.mock.calls[1][1]?.signal as AbortSignal).aborted,
        ).toBe(true);

        newSearch.resolve(
            jsonResponse({
                options: [{ value: 2, label: "Newest" }],
                selected: [],
                nextCursor: null,
            }),
        );
        await flushPromises();
        oldSearch.resolve(
            jsonResponse({
                options: [{ value: 1, label: "Stale" }],
                selected: [],
                nextCursor: null,
            }),
        );
        await flushPromises();

        expect(mounted.wrapper.text()).toContain("Newest");
        expect(mounted.wrapper.text()).not.toContain("Stale");
    });

    it("isolates dependency cache keys and retries failures", async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({ message: "Unavailable" }, 503),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    options: [{ value: 1, label: "Recovered" }],
                    selected: [],
                    nextCursor: null,
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    options: [{ value: 2, label: "Archived" }],
                    selected: [],
                    nextCursor: null,
                }),
            );
        vi.stubGlobal("fetch", fetchMock);
        const filter = ref(remoteFilter());
        let remote!: ReturnType<typeof useRemoteFilterOptions>;
        const Probe = defineComponent({
            setup() {
                remote = useRemoteFilterOptions(filter, ref(null));
                return () => h("div", remote.error.value ?? "ok");
            },
        });
        const mounted = mountWithTableContext(Probe, {
            filters: [filter.value],
            state: {
                search: "",
                sort: "name",
                filters: {
                    status: {
                        enabled: true,
                        clause: "equals",
                        value: "active",
                    },
                },
                columns: {},
                page: 1,
                perPage: 25,
            },
        });

        await flushPromises();
        expect(mounted.wrapper.text()).toContain("Unavailable");

        await remote.retry();
        expect(remote.options.value.map((option) => option.label)).toContain(
            "Recovered",
        );

        mounted.resource.value.state.filters.status.value = "archived";
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(remote.options.value.map((option) => option.label)).toContain(
            "Archived",
        );
    });

    it("isolates cached option pages by signed endpoint", async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({
                    options: [{ value: 1, label: "Categories" }],
                    selected: [],
                    nextCursor: null,
                }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    options: [{ value: 1, label: "Topics" }],
                    selected: [],
                    nextCursor: null,
                }),
            );
        vi.stubGlobal("fetch", fetchMock);
        const firstFilter = ref(remoteFilter("/filter-options/categories"));
        const secondFilter = ref(remoteFilter("/filter-options/topics"));
        let first!: ReturnType<typeof useRemoteFilterOptions>;
        let second!: ReturnType<typeof useRemoteFilterOptions>;
        const Probe = defineComponent({
            setup() {
                first = useRemoteFilterOptions(firstFilter, ref(null));
                second = useRemoteFilterOptions(secondFilter, ref(null));

                return () =>
                    h("div", [
                        ...first.options.value.map((option) => option.label),
                        ...second.options.value.map((option) => option.label),
                    ]);
            },
        });
        const mounted = mountWithTableContext(Probe, {
            filters: [firstFilter.value, secondFilter.value],
        });

        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(mounted.wrapper.text()).toContain("Categories");
        expect(mounted.wrapper.text()).toContain("Topics");
    });
});
