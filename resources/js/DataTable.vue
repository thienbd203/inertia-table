<script setup lang="ts" generic="T extends TableItem">
import { computed, nextTick, ref, toRef, useSlots, watch } from "vue";
import { Confirmation } from "@/components/table/actions";
import { FilterList } from "@/components/table/filters";
import { Pagination, Toolbar, Viewport } from "@/components/table/layout";
import { SlotOutlet } from "@/components/table/shared";
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
function enabledFilterAttributes(resource: TableResource<T>) {
    return Object.entries(resource.state.filters)
        .filter(([, state]) => state.enabled)
        .map(([attribute]) => attribute);
}

const activeFilterAttributes = ref(enabledFilterAttributes(props.resource));
const pendingFilterPopover = ref<string | null>(null);

watch(
    () => enabledFilterAttributes(props.resource),
    (attributes) => {
        activeFilterAttributes.value = [
            ...new Set([...activeFilterAttributes.value, ...attributes]),
        ];
    },
);

async function addFilter(attribute: string) {
    if (activeFilterAttributes.value.includes(attribute)) {
        return;
    }

    activeFilterAttributes.value = [...activeFilterAttributes.value, attribute];

    // The dropdown must unmount before the popover mounts; otherwise its
    // dismissable layer considers the menu selection an outside interaction.
    await nextTick();
    pendingFilterPopover.value = attribute;

    const definition = props.resource.filters.find(
        (filter) => filter.attribute === attribute,
    );
    const clause = definition?.clauses[0];
    if (
        clause &&
        ["is_true", "is_false", "is_set", "is_not_set"].includes(clause)
    ) {
        table.setFilter(attribute, true, clause);
    }
}

function removeFilter(attribute: string) {
    activeFilterAttributes.value = activeFilterAttributes.value.filter(
        (candidate) => candidate !== attribute,
    );
    table.removeFilter(attribute);
}

function consumePendingFilterPopover(attribute: string) {
    if (pendingFilterPopover.value === attribute) {
        pendingFilterPopover.value = null;
    }
}

function clearFilters() {
    activeFilterAttributes.value = [];
    pendingFilterPopover.value = null;
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
    pendingFilterPopover,
    addFilter,
    consumePendingFilterPopover,
    removeFilter,
    clearFilters,
    scope: { table, actions },
});
</script>

<template>
    <div class="tb-wrapper" :aria-busy="table.isNavigating.value">
        <SlotOutlet name="topbar"><Toolbar /></SlotOutlet>
        <SlotOutlet name="filters"><FilterList /></SlotOutlet>
        <Viewport />
        <SlotOutlet v-if="resource.results.total > 0" name="footer">
            <Pagination />
        </SlotOutlet>
        <SlotOutlet
            v-if="actions.pendingAction.value"
            name="confirmation"
            :slot-props="{ pending: actions.pendingAction.value }"
        >
            <Confirmation />
        </SlotOutlet>
    </div>
</template>
