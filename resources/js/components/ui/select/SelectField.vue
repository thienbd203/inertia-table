<script setup lang="ts">
import { SelectValue } from "reka-ui";
import Select from "./Select.vue";
import SelectContent from "./SelectContent.vue";
import SelectItem from "./SelectItem.vue";
import SelectTrigger from "./SelectTrigger.vue";

export type SelectOption = { label: string; value: string };

withDefaults(
    defineProps<{
        modelValue?: string;
        options?: SelectOption[];
        placeholder?: string;
    }>(),
    { modelValue: "", options: () => [], placeholder: "Select…" },
);

defineEmits<{ "update:modelValue": [value: string] }>();
</script>

<template>
    <Select
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', String($event ?? ''))"
    >
        <SelectTrigger>
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
