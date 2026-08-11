<script setup lang="ts">
import { useDebounceFn } from "@vueuse/core";
import { computed, ref, watch } from "vue";
import { Search } from "@lucide/vue";
import { UiInput } from "@/components/ui/input";
import {
    NativeSelect,
    NativeSelectOption,
} from "@/components/ui/native-select";
import type { TableFilter } from "@/types";

const props = defineProps<{
    filter: TableFilter;
    clause: string;
    modelValue: unknown;
    debounceTime: number;
}>();
const emit = defineEmits<{ "update:modelValue": [value: unknown] }>();
const emitInputValue = useDebounceFn(
    (value: unknown) => emit("update:modelValue", value),
    props.debounceTime,
);
const isRange = computed(() =>
    ["between", "not_between"].includes(props.clause),
);
const isValueless = computed(() =>
    ["is_true", "is_false", "is_set", "is_not_set"].includes(props.clause),
);
const control = computed<"none" | "select" | "range" | "input">(() => {
    if (isValueless.value) {
        return "none";
    }

    if (["select", "set"].includes(props.filter.type)) {
        return "select";
    }

    return isRange.value ? "range" : "input";
});
const showsSearchIcon = computed(
    () => control.value === "input" && props.filter.type === "text",
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
const draftRange = ref<[string, string]>(range.value);
const draftInput = ref(String(props.modelValue ?? ""));
const isInputFocused = ref(false);
const valueControl = ref<{ focus: () => void } | null>(null);

watch(range, (nextRange) => {
    draftRange.value = nextRange;
});

watch(
    () => props.modelValue,
    (nextValue) => {
        if (!isInputFocused.value) {
            draftInput.value = String(nextValue ?? "");
        }
    },
);

function updateInput(value: string | number) {
    draftInput.value = String(value);

    emitInputValue(
        props.filter.type === "text" ? draftInput.value.trim() : value,
    );
}

function updateRange(index: 0 | 1, value: string | number) {
    const next = [...draftRange.value] as [string, string];
    next[index] = String(value);
    draftRange.value = next;

    if (next[0] !== "" && next[1] !== "") emitInputValue(next);
}

defineExpose({
    focus: () => valueControl.value?.focus(),
});
</script>

<template>
    <div v-if="control !== 'none'" class="flex items-center gap-2 mt-2">
        <Search v-if="showsSearchIcon" class="size-5" />

        <NativeSelect
            v-if="control === 'select'"
            ref="valueControl"
            class="flex-1"
            :model-value="String(modelValue ?? '')"
            :data-filter-value="filter.attribute"
            @update:model-value="emit('update:modelValue', $event)"
        >
            <NativeSelectOption
                v-for="option in filter.options"
                :key="String(option.value)"
                :value="String(option.value)"
            >
                {{ option.label }}
            </NativeSelectOption>
        </NativeSelect>

        <div v-else-if="control === 'range'" class="tb-filter-range flex-1">
            <UiInput
                ref="valueControl"
                :type="filter.type === 'date' ? 'date' : 'number'"
                :model-value="draftRange[0]"
                :data-filter-value="filter.attribute"
                @update:model-value="(value) => updateRange(0, value)"
            />
            <span aria-hidden="true">–</span>
            <UiInput
                :type="filter.type === 'date' ? 'date' : 'number'"
                :model-value="draftRange[1]"
                @update:model-value="(value) => updateRange(1, value)"
            />
        </div>

        <UiInput
            v-else
            ref="valueControl"
            class="flex-1"
            :type="inputType"
            :model-value="draftInput"
            :data-filter-value="filter.attribute"
            @focus="isInputFocused = true"
            @blur="isInputFocused = false"
            @update:model-value="updateInput"
        />
    </div>
</template>
