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
import { Funnel, Plus, X } from "@lucide/vue";

defineProps<{
    filters: TableFilter[];
    activeAttributes: string[];
}>();
defineEmits<{
    add: [attribute: string];
    clear: [];
}>();
</script>

<template>
    <UiDropdownMenu v-if="filters.length">
        <UiDropdownMenuTrigger>
            <UiButton variant="outline">
                <Funnel class="h-4 w-4" />
                Filters
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent
            align="end"
            @close-auto-focus="(e) => e.preventDefault()"
        >
            <UiDropdownMenuLabel>Add Filter</UiDropdownMenuLabel>
            <UiDropdownMenuSeparator />
            <UiDropdownMenuItem
                v-for="filter in filters"
                :key="filter.attribute"
                :disabled="activeAttributes.includes(filter.attribute)"
                @select="$emit('add', filter.attribute)"
            >
                <Plus
                    class="size-4"
                    v-if="!activeAttributes.includes(filter.attribute)"
                />
                <div v-else class="size-4"></div>
                {{ filter.label }}
            </UiDropdownMenuItem>
            <template v-if="activeAttributes.length">
                <UiDropdownMenuSeparator />
                <UiDropdownMenuItem @select="$emit('clear')">
                    <X class="size-4" />
                    Clear all filters
                </UiDropdownMenuItem>
            </template>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
