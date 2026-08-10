<script setup lang="ts">
import { DropdownMenuItem } from "reka-ui";

defineProps<{ disabled?: boolean }>();
defineEmits<{ select: [event: Event] }>();
</script>

<template>
    <DropdownMenuItem
        data-slot="dropdown-menu-item"
        :disabled="disabled"
        @select="$emit('select', $event)"
    >
        <slot />
    </DropdownMenuItem>
</template>
