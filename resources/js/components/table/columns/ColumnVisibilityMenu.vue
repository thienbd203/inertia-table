<script setup lang="ts">
import { computed } from "vue";
import { UiButton } from "@/components/ui/button";
import { Eye, RotateCcw } from "@lucide/vue";
import {
    UiDropdownMenu,
    UiDropdownMenuCheckboxItem,
    UiDropdownMenuContent,
    UiDropdownMenuItem,
    UiDropdownMenuLabel,
    UiDropdownMenuSeparator,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTableContext } from "@/context/tableContext";

const { resource, table, i18n } = useTableContext();
const columns = computed(() =>
    table.orderedColumns.value.filter((column) => column.toggleable),
);
const hasLayout = computed(
    () =>
        resource.value.capabilities.hasResizableColumns === true ||
        resource.value.capabilities.hasReorderableColumns === true,
);
</script>

<template>
    <UiDropdownMenu v-if="columns.length || hasLayout">
        <UiDropdownMenuTrigger>
            <UiButton variant="outline">
                <Eye class="h-4 w-4" />
                {{ i18n.t("columns") }}
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent align="end">
            <UiDropdownMenuLabel>{{ i18n.t("columns") }}</UiDropdownMenuLabel>
            <UiDropdownMenuSeparator />
            <UiDropdownMenuCheckboxItem
                v-for="column in columns"
                :key="column.attribute"
                :model-value="
                    resource.state.columns[column.attribute] !== false
                "
                @update:model-value="table.toggleColumn(column.attribute)"
            >
                {{ column.header }}
            </UiDropdownMenuCheckboxItem>
            <template v-if="hasLayout">
                <UiDropdownMenuSeparator v-if="columns.length" />
                <UiDropdownMenuItem @select="table.resetColumnLayout">
                    <RotateCcw class="size-4" />
                    {{ i18n.t("resetColumnLayout") }}
                </UiDropdownMenuItem>
            </template>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
