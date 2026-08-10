<script setup lang="ts">
import { Check } from "@lucide/vue";
import {
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    type AcceptableValue,
} from "reka-ui";

defineProps<{ value: AcceptableValue }>();
</script>

<template>
    <SelectItem data-slot="select-item" :value="value">
        <SelectItemText><slot /></SelectItemText>
        <SelectItemIndicator data-slot="select-item-indicator">
            <Check :size="14" />
        </SelectItemIndicator>
    </SelectItem>
</template>
