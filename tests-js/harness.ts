import { mount } from "@vue/test-utils";
import { defineComponent, h, ref, type Component } from "vue";
import { provideTableContext } from "../resources/js/context/tableContext";
import type { TableResource } from "../resources/js/types";
import { useActions } from "../resources/js/useActions";
import { useExports } from "../resources/js/useExports";
import { useTable } from "../resources/js/useTable";
import { useStickyColumns } from "../resources/js/useStickyColumns";
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
    let sticky!: ReturnType<typeof useStickyColumns<Topic>>;
    let tableExports!: ReturnType<typeof useExports<Topic>>;
    let views!: ReturnType<typeof useViews<Topic>>;

    const wrapper = mount(
        defineComponent({
            setup() {
                table = useTable(resource);
                sticky = useStickyColumns(table);
                actions = useActions(table);
                tableExports = useExports(table, actions);
                views = useViews(table);
                const i18n = useTableI18n();

                provideTableContext({
                    resource,
                    table,
                    sticky,
                    actions,
                    exports: tableExports,
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
                    scope: {
                        table,
                        sticky,
                        actions,
                        exports: tableExports,
                        views,
                    },
                });

                return () => h(component);
            },
        }),
        { attachTo: document.body },
    );

    return {
        actions: actions!,
        exports: tableExports!,
        resource,
        table: table!,
        sticky: sticky!,
        views: views!,
        wrapper,
    };
}
