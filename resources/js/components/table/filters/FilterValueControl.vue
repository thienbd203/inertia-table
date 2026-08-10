<script setup lang="ts">
import { computed } from "vue";
import { Filter, Search } from "@lucide/vue";
import { UiInput } from "@/components/ui/input";
import { UiSelect } from "@/components/ui/select";
import type { TableFilter } from "@/types";

const props = defineProps<{
    filter: TableFilter;
    clause: string;
    modelValue: unknown;
}>();
const emit = defineEmits<{ "update:modelValue": [value: unknown] }>();
const isRange = computed(() =>
    ["between", "not_between"].includes(props.clause),
);
const isValueless = computed(() =>
    ["is_true", "is_false", "is_set", "is_not_set"].includes(props.clause),
);
const inputType = computed<"date" | "number" | "text">(() =>
    props.filter.type === "date"
        ? "date"
        : props.filter.type === "numeric"
          ? "number"
          : "text",
);
const range = computed<[string, string]>(() => {
    const value = Array.isArray(props.modelValue) ? props.modelValue : [];
    return [String(value[0] ?? ""), String(value[1] ?? "")];
});
function updateRange(index: 0 | 1, value: string | number) {
    const next = [...range.value] as [string, string];
    next[index] = String(value);
    if (next[0] !== "" && next[1] !== "") emit("update:modelValue", next);
}
</script>

<template>
    <div class="flex items-center gap-2">
        <Search class="size-5" />
        <div class="flex-1">
            <div
                v-if="filter.type === 'select' || filter.type === 'set'"
                class="flex items-center gap-2"
            >
                <UiSelect
                    :model-value="String(modelValue ?? '')"
                    :options="
                        filter.options.map((option) => ({
                            ...option,
                            value: String(option.value),
                        }))
                    "
                    @update:model-value="emit('update:modelValue', $event)"
                />
            </div>
            <span v-else-if="isValueless" class="tb-filter-clause-only"
                >This filter does not require a value.</span
            >
            <div v-else-if="isRange" class="tb-filter-range">
                <UiInput
                    :type="filter.type === 'date' ? 'date' : 'number'"
                    :model-value="range[0]"
                    @update:model-value="(value) => updateRange(0, value)"
                />
                <span aria-hidden="true">–</span>
                <UiInput
                    :type="filter.type === 'date' ? 'date' : 'number'"
                    :model-value="range[1]"
                    @update:model-value="(value) => updateRange(1, value)"
                />
            </div>
            <UiInput
                v-else
                :type="inputType"
                :model-value="String(modelValue ?? '')"
                @update:model-value="emit('update:modelValue', $event)"
            />
        </div>
    </div>
</template>
