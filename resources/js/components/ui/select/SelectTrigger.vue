<script setup lang="ts">
import { ChevronDown } from "@lucide/vue";
import { SelectIcon, SelectTrigger } from "reka-ui";
</script>

<template>
    <SelectTrigger data-slot="select-trigger">
        <slot />
        <SelectIcon as-child><ChevronDown :size="16" /></SelectIcon>
    </SelectTrigger>
</template>
