<script setup lang="ts">
import { computed } from "vue";
import { useTableContext } from "../../context/tableContext";
import { UiButton } from "../ui/button";
import {
    UiDropdownMenu,
    UiDropdownMenuCheckboxItem,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "../ui/dropdown-menu";

const { resource, table } = useTableContext();
const columns = computed(() =>
    resource.value.columns.filter((column) => column.toggleable),
);
</script>

<template>
    <UiDropdownMenu v-if="columns.length">
        <UiDropdownMenuTrigger>
            <UiButton variant="outline">Columns</UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent>
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
