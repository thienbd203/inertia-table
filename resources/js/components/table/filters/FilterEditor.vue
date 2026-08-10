<script setup lang="ts">
import type { TableFilter } from "@/types";
import { SlotOutlet } from "../shared";
import FilterClauseSelect from "./FilterClauseSelect.vue";
import FilterValueControl from "./FilterValueControl.vue";
import { useFilterEditor } from "./useFilterEditor";

const props = defineProps<{ filter: TableFilter }>();
const emit = defineEmits<{ "update:displayValue": [value: string | null] }>();
const { clause, clauseOptions, state, table, update, updateClause, value } =
    useFilterEditor(props.filter);
</script>

<template>
    <SlotOutlet
        :name="`filter(${filter.attribute})`"
        :slot-props="{
            filter,
            state,
            update,
            setDisplayValue: (value: string | null) =>
                emit('update:displayValue', value),
            table,
        }"
    >
        <div class="tb-filter-editor space-y-2">
            <FilterClauseSelect
                v-if="filter.showClause !== false && clauseOptions.length > 1"
                :model-value="clause"
                :options="clauseOptions"
                @update:model-value="updateClause"
            />
            <FilterValueControl
                :filter="filter"
                :clause="clause"
                :model-value="value"
                @update:model-value="update"
            />
        </div>
    </SlotOutlet>
</template>
