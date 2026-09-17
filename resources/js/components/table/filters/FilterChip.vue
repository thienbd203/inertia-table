<script setup lang="ts">
import { computed } from "vue";
import { clauseSymbol, filterDisplayValue } from "@/filters";
import type { TableFilter, TableFilterState } from "@/types";
import { useTableContext } from "@/context/tableContext";

const props = defineProps<{
    filter: TableFilter;
    state?: TableFilterState;
    displayValue?: string | null;
}>();
const { i18n } = useTableContext();
const display = computed(
    () => props.displayValue ?? filterDisplayValue(props.filter, props.state),
);
</script>

<template>
    <button
        type="button"
        class="tb-filter-chip space-x-1 ps-2 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :aria-label="i18n.t('editFilter', { filter: filter.label })"
    >
        <span>{{ filter.label }}</span>
        <span class="tb-filter-symbol">
            {{ clauseSymbol(state?.clause ?? filter.clauses[0] ?? "equals") }}
        </span>
        <em v-if="display">{{ display }}</em>
    </button>
</template>
