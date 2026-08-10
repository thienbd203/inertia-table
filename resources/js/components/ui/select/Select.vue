<script setup lang="ts">
import {
    SelectRoot,
    type SelectRootEmits,
    type SelectRootProps,
} from "reka-ui";

defineProps<SelectRootProps>();
defineEmits<SelectRootEmits>();
</script>

<template>
    <SelectRoot
        v-bind="$props"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <slot />
    </SelectRoot>
</template>
