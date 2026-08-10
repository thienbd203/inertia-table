<script setup lang="ts">
import { computed } from "vue";
import { UiInput } from "@/components/ui/input";
import { useTableContext } from "@/context/tableContext";
import ColumnVisibilityMenu from "./ColumnVisibilityMenu.vue";
import AddFilterMenu from "./AddFilterMenu.vue";
import SlotOutlet from "./SlotOutlet";
import TableActionsMenu from "./TableActionsMenu.vue";

const {
    resource,
    table,
    searchPlaceholder,
    activeFilterAttributes,
    addFilter,
} = useTableContext();

const availableFilters = computed(() =>
    resource.value.filters.filter(
        (filter) => !activeFilterAttributes.value.includes(filter.attribute),
    ),
);
</script>

<template>
    <div class="tb-topbar flex items-center justify-between">
        <div class="tb-search-group">
            <SlotOutlet name="beforeSearch" />
            <UiInput
                v-if="resource.capabilities.searchable"
                type="search"
                :model-value="table.search.value"
                :placeholder="searchPlaceholder"
                @update:model-value="(value) => table.setSearch(String(value))"
                class="w-[200px]"
            />
            <SlotOutlet name="afterSearch" />
        </div>

        <div class="tb-action-group flex gap-2">
            <SlotOutlet name="beforeActions" />
            <TableActionsMenu />
            <AddFilterMenu :filters="availableFilters" @add="addFilter" />
            <ColumnVisibilityMenu />
            <SlotOutlet name="afterActions" />
        </div>
    </div>
</template>
