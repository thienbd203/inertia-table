<script setup lang="ts">
import { UiButton } from "@/components/ui/button";
import { UiCheckbox } from "@/components/ui/checkbox";
import { UiTableHead, UiTableHeader, UiTableRow } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import SlotOutlet from "./SlotOutlet";

defineProps<{ canSelect: boolean }>();
const { resource, table, actions } = useTableContext();

function sortIndicator(attribute: string): string {
    if (resource.value.state.sort === attribute) return " ↑";
    if (resource.value.state.sort === `-${attribute}`) return " ↓";
    return "";
}
</script>

<template>
    <UiTableHeader>
        <UiTableRow>
            <UiTableHead v-if="canSelect" class="tb-selection-cell">
                <UiCheckbox
                    aria-label="Select current page"
                    :model-value="actions.allItemsAreSelected.value"
                    @update:model-value="actions.toggleAll"
                />
            </UiTableHead>
            <UiTableHead
                v-for="column in table.visibleColumns.value"
                :key="column.attribute"
                :data-alignment="column.alignment"
                :class="column.headerClass"
                :title="column.tooltip ?? undefined"
            >
                <SlotOutlet
                    :name="`header(${column.attribute})`"
                    :slot-props="{ column }"
                >
                    <UiButton
                        v-if="column.sortable"
                        variant="ghost"
                        size="sm"
                        class="tb-sort-button"
                        @click="table.setSort(column.attribute)"
                    >
                        {{ column.header }}{{ sortIndicator(column.attribute) }}
                    </UiButton>
                    <span v-else>{{ column.header }}</span>
                </SlotOutlet>
            </UiTableHead>
        </UiTableRow>
    </UiTableHeader>
</template>
