<script setup lang="ts">
import { computed } from "vue";
import { UiButton } from "@/components/ui/button";
import { Eye } from "@lucide/vue";
import {
    UiDropdownMenu,
    UiDropdownMenuCheckboxItem,
    UiDropdownMenuContent,
    UiDropdownMenuLabel,
    UiDropdownMenuSeparator,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTableContext } from "@/context/tableContext";

const { resource, table, i18n } = useTableContext();
const columns = computed(() =>
    resource.value.columns.filter((column) => column.toggleable),
);
</script>

<template>
    <UiDropdownMenu v-if="columns.length">
        <UiDropdownMenuTrigger>
            <UiButton variant="outline">
                <Eye class="h-4 w-4" />
                {{ i18n.t("columns") }}
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent>
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
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
