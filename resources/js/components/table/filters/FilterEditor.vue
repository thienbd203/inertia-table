<script setup lang="ts">
import { Filter } from "@lucide/vue";
import { UiInput } from "@/components/ui/input";
import { UiSelect } from "@/components/ui/select";
import type { TableFilter } from "@/types";
import { SlotOutlet } from "../shared";
import { useFilterEditor } from "./useFilterEditor";

const props = defineProps<{ filter: TableFilter }>();
const emit = defineEmits<{ "update:displayValue": [value: string | null] }>();
const {
    clause,
    clauseOptions,
    isRangeClause,
    isValuelessClause,
    rangeValue,
    state,
    table,
    update,
    updateClause,
    updateRangeValue,
    value,
    valueOptions,
} = useFilterEditor(props.filter);
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
        <div class="tb-filter-editor gap-2">
            <div
                v-if="filter.showClause !== false && clauseOptions.length > 1"
                class="flex items-center gap-2"
            >
                <Filter class="size-5" />
                <UiSelect
                    :model-value="clause"
                    :options="clauseOptions"
                    class="w-full"
                    @update:model-value="updateClause"
                />
            </div>
            <div
                v-if="filter.type === 'select' || filter.type === 'set'"
                class="flex items-center gap-2"
            >
                <Filter class="size-5" />
                <UiSelect
                    :model-value="String(value)"
                    :options="valueOptions"
                    class="w-full"
                    @update:model-value="update"
                />
            </div>
            <span v-else-if="isValuelessClause" class="tb-filter-clause-only">
                This filter does not require a value.
            </span>
            <div v-else-if="isRangeClause" class="tb-filter-range">
                <UiInput
                    :type="filter.type === 'date' ? 'date' : 'number'"
                    :model-value="rangeValue(0)"
                    @update:model-value="(value) => updateRangeValue(0, value)"
                />
                <span aria-hidden="true">–</span>
                <UiInput
                    :type="filter.type === 'date' ? 'date' : 'number'"
                    :model-value="rangeValue(1)"
                    @update:model-value="(value) => updateRangeValue(1, value)"
                />
            </div>
            <UiInput
                v-else
                :type="
                    filter.type === 'date'
                        ? 'date'
                        : filter.type === 'numeric'
                          ? 'number'
                          : 'text'
                "
                :model-value="String(value)"
                @update:model-value="update"
            />
        </div>
    </SlotOutlet>
</template>
