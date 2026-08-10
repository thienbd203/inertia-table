<script setup lang="ts">
import { X } from "@lucide/vue";
import { computed } from "vue";
import { clauseSymbol, filterDisplayValue } from "@/filters";
import type { TableFilter, TableFilterState } from "@/types";

const props = defineProps<{
    filter: TableFilter;
    state?: TableFilterState;
    displayValue?: string | null;
}>();
const emit = defineEmits<{ remove: [] }>();
const display = computed(
    () => props.displayValue ?? filterDisplayValue(props.filter, props.state),
);
</script>

<template>
    <div
        class="tb-filter-chip space-x-1 py-1 ps-2 text-sm font-medium"
        role="button"
        tabindex="0"
        :aria-label="`Edit ${filter.label} filter`"
    >
        <span>{{ filter.label }}</span>
        <span class="tb-filter-symbol">
            {{ clauseSymbol(state?.clause ?? filter.clauses[0] ?? "equals") }}
        </span>
        <em v-if="display">{{ display }}</em>
        <button
            type="button"
            class="tb-remove-filter ms-2 h-full py-1 pe-2 text-gray-500 transition-colors hover:text-red-500"
            :aria-label="`Remove ${filter.label} filter`"
            @click.stop="emit('remove')"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
