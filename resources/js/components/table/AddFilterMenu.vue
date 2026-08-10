<script setup lang="ts">
import { UiButton } from "@/components/ui/button";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuItem,
    UiDropdownMenuLabel,
    UiDropdownMenuSeparator,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { TableFilter } from "@/types";

defineProps<{ filters: TableFilter[] }>();
defineEmits<{ add: [attribute: string] }>();
</script>

<template>
    <UiDropdownMenu v-if="filters.length">
        <UiDropdownMenuTrigger>
            <UiButton variant="outline">Filters</UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent>
            <UiDropdownMenuLabel>Add Filter</UiDropdownMenuLabel>
            <UiDropdownMenuSeparator />
            <UiDropdownMenuItem
                v-for="filter in filters"
                :key="filter.attribute"
                @select="$emit('add', filter.attribute)"
            >
                {{ filter.label }}
            </UiDropdownMenuItem>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
