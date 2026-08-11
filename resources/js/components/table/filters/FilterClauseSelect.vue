<script setup lang="ts">
import { Filter } from "@lucide/vue";
import {
    NativeSelect,
    NativeSelectOption,
} from "@/components/ui/native-select";

defineProps<{
    modelValue: string;
    options: Array<{ label: string; value: string }>;
}>();
const emit = defineEmits<{ "update:modelValue": [value: string] }>();

function updateClause(value: unknown) {
    emit("update:modelValue", String(value ?? ""));
}
</script>

<template>
    <div class="flex items-center gap-2">
        <div>
            <Filter class="size-5" />
        </div>
        <NativeSelect
            :model-value="modelValue"
            @update:model-value="updateClause"
        >
            <NativeSelectOption
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </NativeSelectOption>
        </NativeSelect>
    </div>
</template>
