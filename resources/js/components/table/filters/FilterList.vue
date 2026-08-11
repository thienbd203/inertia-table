<script setup lang="ts">
import { useTableContext } from "@/context/tableContext";
import { computed } from "vue";
import ActiveFilter from "./ActiveFilter.vue";

const {
    resource,
    activeFilterAttributes,
    pendingFilterPopover,
    consumePendingFilterPopover,
    removeFilter,
} = useTableContext();
const activeFilters = computed(() =>
    activeFilterAttributes.value
        .map((attribute) =>
            resource.value.filters.find(
                (filter) => filter.attribute === attribute,
            ),
        )
        .filter((filter) => filter !== undefined),
);
</script>

<template>
    <div
        v-if="activeFilters.length"
        class="tb-filters flex items-center gap-2 mt-4"
    >
        <ActiveFilter
            v-for="filter in activeFilters"
            :key="filter.attribute"
            :filter="filter"
            :auto-open="pendingFilterPopover === filter.attribute"
            @opened="consumePendingFilterPopover(filter.attribute)"
            @remove="removeFilter(filter.attribute)"
        />
    </div>
</template>
