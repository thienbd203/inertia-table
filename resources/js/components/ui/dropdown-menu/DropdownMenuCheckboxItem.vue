<script setup lang="ts">
import { Check } from "@lucide/vue";
import { DropdownMenuCheckboxItem, DropdownMenuItemIndicator } from "reka-ui";

defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{ "update:modelValue": [value: boolean] }>();
</script>

<template>
    <DropdownMenuCheckboxItem
        data-slot="dropdown-menu-checkbox-item"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event === true)"
    >
        <span data-slot="dropdown-menu-item-indicator">
            <DropdownMenuItemIndicator
                ><Check :size="14"
            /></DropdownMenuItemIndicator>
        </span>
        <slot />
    </DropdownMenuCheckboxItem>
</template>
