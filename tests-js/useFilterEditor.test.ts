import { defineComponent, h, ref } from "vue";
import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

vi.mock("@inertiajs/vue3", () => ({
    router: {
        visit: vi.fn(),
        on: vi.fn(() => vi.fn()),
    },
    usePage: () => ({ url: "/admin/topics" }),
}));

import { useFilterEditor } from "../resources/js/components/table/filters/useFilterEditor";
import { provideTableContext } from "../resources/js/context/tableContext";
import { useActions } from "../resources/js/useActions";
import { useTable } from "../resources/js/useTable";
import { useViews } from "../resources/js/useViews";
import type { TableFilter } from "../resources/js/types";
import { useTableI18n } from "../resources/js/i18n";

const scoreFilter: TableFilter = {
    attribute: "score",
    label: "Score",
    type: "numeric",
    clauses: ["equals", "between", "not_between"],
    options: [],
    meta: {},
};

const featuredFilter: TableFilter = {
    attribute: "is_featured",
    label: "Featured",
    type: "boolean",
    clauses: ["is_true", "is_false"],
    options: [],
    meta: {},
};

function resourceWithFilters(filters: TableFilter[]) {
    const base = topicResource();

    return {
        ...base,
        filters,
        state: {
            ...base.state,
            filters: Object.fromEntries(
                filters.map((filter) => [
                    filter.attribute,
                    { enabled: false, clause: filter.clauses[0], value: null },
                ]),
            ),
        },
    };
}

function mountEditor(filter: TableFilter, filters: TableFilter[]) {
    const resource = ref(resourceWithFilters(filters));
    let table!: ReturnType<typeof useTable<Topic>>;
    let actions!: ReturnType<typeof useActions<Topic>>;
    let editor!: ReturnType<typeof useFilterEditor>;

    // `useFilterEditor` injects the context that `<DataTable>` provides, so
    // it must run in a child component — provide() and inject() never
    // resolve within the same component instance.
    const FilterEditorHost = defineComponent({
        setup() {
            editor = useFilterEditor(filter);

            return () => h("div");
        },
    });

    mount(
        defineComponent({
            setup() {
                table = useTable(resource);
                actions = useActions(table);
                const views = useViews(table);
                const i18n = useTableI18n();
                provideTableContext({
                    resource,
                    table,
                    actions,
                    views,
                    iconResolver: undefined,
                    i18n,
                    searchPlaceholder: ref("Search…"),
                    slots: {},
                    activeFilterAttributes: ref([]),
                    pendingFilterPopover: ref(null),
                    addFilter: () => {},
                    consumePendingFilterPopover: () => {},
                    removeFilter: () => {},
                    clearFilters: () => {},
                    scope: { table, actions, views },
                });

                return () => h(FilterEditorHost);
            },
        }),
    );

    return { editor: editor!, table: table! };
}

describe("useFilterEditor", () => {
    it("resets the value to an empty range and does not submit yet when switching to a range clause", () => {
        const { editor, table } = mountEditor(scoreFilter, [scoreFilter]);
        const setFilter = vi.spyOn(table, "setFilter");

        editor.updateClause("between");

        expect(editor.isRangeClause.value).toBe(true);
        expect(editor.value.value).toEqual(["", ""]);
        expect(setFilter).not.toHaveBeenCalled();
    });

    it("only submits a range filter once both sides are filled in", () => {
        const { editor, table } = mountEditor(scoreFilter, [scoreFilter]);
        const setFilter = vi.spyOn(table, "setFilter");

        editor.updateClause("between");
        editor.updateRangeValue(0, 15);
        expect(setFilter).not.toHaveBeenCalled();

        editor.updateRangeValue(1, 35);
        expect(setFilter).toHaveBeenCalledWith(
            "score",
            ["15", "35"],
            "between",
        );
    });

    it("resets back to a plain empty value when leaving a range clause", () => {
        const { editor } = mountEditor(scoreFilter, [scoreFilter]);

        editor.updateClause("between");
        editor.updateRangeValue(0, 15);
        editor.updateClause("equals");

        expect(editor.isRangeClause.value).toBe(false);
        expect(editor.value.value).toBe("");
    });

    it("submits a valueless clause immediately", () => {
        const { editor, table } = mountEditor(featuredFilter, [featuredFilter]);
        const setFilter = vi.spyOn(table, "setFilter");

        editor.updateClause("is_true");

        expect(editor.isValuelessClause.value).toBe(true);
        expect(setFilter).toHaveBeenCalledWith("is_featured", true, "is_true");
    });
});
