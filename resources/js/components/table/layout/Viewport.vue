<script setup lang="ts">
import { computed } from "vue";
import { UiTable } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import type { TableColumn, TableItem } from "@/types";
import { Header } from "../columns";
import { Body } from "../rows";
import { SlotOutlet } from "../shared";
import { SummaryFooter } from "../summary";

const { resource, actions } = useTableContext();
const emit = defineEmits<{
    rowClick: [item: TableItem, column: TableColumn | null];
}>();
const canSelect = computed(
    () =>
        resource.value.capabilities.selectable &&
        (actions.bulkActions.value.length > 0 ||
            resource.value.exports?.some(
                (definition) => definition.requiresSelection,
            ) === true),
);
</script>

<template>
    <SlotOutlet name="table">
        <UiTable
            class="tb-table"
            :container-class="{
                'tb-sticky-header-container': resource.options.stickyHeader,
                'tb-sticky-backdrop-filter':
                    resource.options.stickyBackdropFilter ?? true,
            }"
        >
            <SlotOutlet name="thead">
                <Header :can-select="canSelect" />
            </SlotOutlet>
            <SlotOutlet name="tbody">
                <Body
                    :can-select="canSelect"
                    @row-click="
                        (item, column) => emit('rowClick', item, column)
                    "
                />
            </SlotOutlet>
            <SlotOutlet
                v-if="resource.capabilities.hasSummaries"
                name="summaryFooter"
            >
                <SummaryFooter :can-select="canSelect" />
            </SlotOutlet>
        </UiTable>
    </SlotOutlet>
</template>
