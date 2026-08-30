import { mount } from "@vue/test-utils";
import { defineComponent, h, ref, type Component } from "vue";
import { provideTableContext } from "../resources/js/context/tableContext";
import type { TableResource } from "../resources/js/types";
import { useActions } from "../resources/js/useActions";
import { useTable } from "../resources/js/useTable";
import { useViews } from "../resources/js/useViews";
import { useTableI18n } from "../resources/js/i18n";
import type { Topic } from "./fixtures";
import { topicResource } from "./fixtures";

/**
 * Mounts a real component from `components/table/*` behind the same
 * TableContext that `<DataTable>` provides, using the real `useTable`/
 * `useActions` composables (against the mocked `@inertiajs/vue3` router)
 * instead of hand-rolled fakes.
 */
export function mountWithTableContext(
    component: Component,
    resourceOverrides: Partial<TableResource<Topic>> = {},
) {
    const resource = ref(topicResource(resourceOverrides));
    let table!: ReturnType<typeof useTable<Topic>>;
    let actions!: ReturnType<typeof useActions<Topic>>;
    let views!: ReturnType<typeof useViews<Topic>>;

    const wrapper = mount(
        defineComponent({
            setup() {
                table = useTable(resource);
                actions = useActions(table);
                views = useViews(table);
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

                return () => h(component);
            },
        }),
        { attachTo: document.body },
    );

    return {
        actions: actions!,
        resource,
        table: table!,
        views: views!,
        wrapper,
    };
}
