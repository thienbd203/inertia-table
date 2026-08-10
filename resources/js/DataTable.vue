<script setup lang="ts" generic="T extends TableItem">
import { computed, ref, toRef, useSlots, watch } from "vue";
import TableConfirmation from "@/components/table/TableConfirmation.vue";
import TableFilters from "@/components/table/TableFilters.vue";
import TablePagination from "@/components/table/TablePagination.vue";
import SlotOutlet from "@/components/table/SlotOutlet";
import TableToolbar from "@/components/table/TableToolbar.vue";
import TableViewport from "@/components/table/TableViewport.vue";
import { provideTableContext } from "@/context/tableContext";
import type { IconResolver } from "@/icons";
import "@/styles/data-table.css";
import type { TableItem, TableResource } from "@/types";
import { useActions } from "@/useActions";
import { useTable } from "@/useTable";

const props = withDefaults(
    defineProps<{
        resource: TableResource<T>;
        searchPlaceholder?: string;
        iconResolver?: IconResolver;
    }>(),
    { searchPlaceholder: "Search…" },
);

const resource = toRef(props, "resource");
const table = useTable(resource);
const actions = useActions(table);
const activeFilterAttributes = ref(Object.keys(props.resource.state.filters));

watch(
    () => Object.keys(props.resource.state.filters),
    (attributes) => {
        activeFilterAttributes.value = [
            ...new Set([...activeFilterAttributes.value, ...attributes]),
        ];
    },
);

function addFilter(attribute: string) {
    if (!activeFilterAttributes.value.includes(attribute)) {
        activeFilterAttributes.value = [
            ...activeFilterAttributes.value,
            attribute,
        ];
    }
}

function removeFilter(attribute: string) {
    activeFilterAttributes.value = activeFilterAttributes.value.filter(
        (candidate) => candidate !== attribute,
    );
    table.removeFilter(attribute);
}

function clearFilters() {
    activeFilterAttributes.value = [];
    table.clearFilters();
}

provideTableContext({
    resource,
    table,
    actions,
    iconResolver: props.iconResolver,
    searchPlaceholder: computed(() => props.searchPlaceholder),
    slots: useSlots(),
    activeFilterAttributes,
    addFilter,
    removeFilter,
    clearFilters,
    scope: { table, actions },
});
</script>

<template>
    <div class="tb-wrapper" :aria-busy="table.isNavigating.value">
        <SlotOutlet name="topbar"><TableToolbar /></SlotOutlet>
        <SlotOutlet name="filters"><TableFilters /></SlotOutlet>
        <TableViewport />
        <SlotOutlet v-if="resource.results.total > 0" name="footer">
            <TablePagination />
        </SlotOutlet>
        <SlotOutlet
            v-if="actions.pendingAction.value"
            name="confirmation"
            :slot-props="{ pending: actions.pendingAction.value }"
        >
            <TableConfirmation />
        </SlotOutlet>
    </div>
</template>
