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
import { useTableContext } from "@/context/tableContext";
import { Funnel, Plus, X } from "@lucide/vue";
import { ref } from "vue";

defineProps<{
    filters: TableFilter[];
    activeAttributes: string[];
}>();
defineEmits<{
    add: [attribute: string];
    clear: [];
}>();
const { i18n } = useTableContext();
const openingEditor = ref(false);

function restoreFocus(event: Event) {
    // A newly added editor owns focus; Escape and Clear still return to Filters.
    if (openingEditor.value) event.preventDefault();
    openingEditor.value = false;
}
</script>

<template>
    <UiDropdownMenu v-if="filters.length">
        <UiDropdownMenuTrigger as-child>
            <UiButton variant="outline" data-add-filter-trigger>
                <Funnel class="h-4 w-4" />
                {{ i18n.t("filters") }}
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent
            align="start"
            class="DropdownMenuContentAnimate"
            @close-auto-focus="restoreFocus"
        >
            <UiDropdownMenuLabel>{{ i18n.t("addFilter") }}</UiDropdownMenuLabel>
            <UiDropdownMenuSeparator />
            <UiDropdownMenuItem
                v-for="filter in filters"
                :key="filter.attribute"
                :disabled="activeAttributes.includes(filter.attribute)"
                @select="
                    openingEditor = true;
                    $emit('add', filter.attribute);
                "
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
                    {{ i18n.t("clearAllFilters") }}
                </UiDropdownMenuItem>
            </template>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
