<script setup lang="ts">
import { UiButton } from "@/components/ui/button";
import { useTableContext } from "@/context/tableContext";
import { computed } from "vue";
import ActiveFilter from "./ActiveFilter.vue";

const { resource, activeFilterAttributes, removeFilter, clearFilters } =
    useTableContext();
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
    <div v-if="activeFilters.length" class="tb-filters">
        <ActiveFilter
            v-for="filter in activeFilters"
            :key="filter.attribute"
            :filter="filter"
            @remove="removeFilter(filter.attribute)"
        />
        <UiButton variant="ghost" size="sm" @click="clearFilters">
            Clear filters
        </UiButton>
    </div>
</template>
