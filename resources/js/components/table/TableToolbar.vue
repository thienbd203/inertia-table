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
    <div class="tb-topbar">
        <div class="tb-search-group">
            <SlotOutlet name="beforeSearch" />
            <UiInput
                v-if="resource.capabilities.searchable"
                type="search"
                :model-value="table.search.value"
                :placeholder="searchPlaceholder"
                @update:model-value="table.setSearch"
            />
            <SlotOutlet name="afterSearch" />
        </div>

        <div class="tb-action-group">
            <SlotOutlet name="beforeActions" />
            <TableActionsMenu />
            <AddFilterMenu :filters="availableFilters" @add="addFilter" />
            <ColumnVisibilityMenu />
            <SlotOutlet name="afterActions" />
        </div>
    </div>
</template>
