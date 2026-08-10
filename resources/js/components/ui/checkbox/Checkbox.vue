<script setup lang="ts">
import { Check } from "@lucide/vue";
import { CheckboxIndicator, CheckboxRoot } from "reka-ui";

defineProps<{ modelValue?: boolean }>();
const emit = defineEmits<{ "update:modelValue": [value: boolean] }>();
</script>

<template>
    <CheckboxRoot
        data-slot="checkbox"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event === true)"
    >
        <CheckboxIndicator data-slot="checkbox-indicator">
            <Check :size="14" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
