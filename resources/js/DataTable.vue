<script setup lang="ts" generic="T extends TableItem">
import { computed, toRef, useSlots } from "vue";
import TableConfirmation from "./components/table/TableConfirmation.vue";
import TableFilters from "./components/table/TableFilters.vue";
import TablePagination from "./components/table/TablePagination.vue";
import SlotOutlet from "./components/table/SlotOutlet";
import TableToolbar from "./components/table/TableToolbar.vue";
import TableViewport from "./components/table/TableViewport.vue";
import { provideTableContext } from "./context/tableContext";
import type { IconResolver } from "./icons";
import type { TableItem, TableResource } from "./types";
import { useActions } from "./useActions";
import { useTable } from "./useTable";
import "./styles/data-table.css";

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

provideTableContext({
    resource,
    table,
    actions,
    iconResolver: props.iconResolver,
    searchPlaceholder: computed(() => props.searchPlaceholder),
    slots: useSlots(),
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
