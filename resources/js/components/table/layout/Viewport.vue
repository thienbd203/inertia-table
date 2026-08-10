<script setup lang="ts">
import { computed } from "vue";
import { UiTable } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import { Header } from "../columns";
import { Body } from "../rows";
import { SlotOutlet } from "../shared";

const { resource, table, actions } = useTableContext();
const canSelect = computed(
    () =>
        resource.value.capabilities.selectable &&
        actions.bulkActions.value.length > 0,
);
</script>

<template>
    <div class="tb-table-container">
        <SlotOutlet name="table">
            <UiTable class="tb-table">
                <SlotOutlet name="thead">
                    <Header :can-select="canSelect" />
                </SlotOutlet>
                <SlotOutlet name="tbody">
                    <Body :can-select="canSelect" />
                </SlotOutlet>
            </UiTable>
        </SlotOutlet>

        <SlotOutlet v-if="table.isNavigating.value" name="loading">
            <div class="tb-loading" role="status">Loading…</div>
        </SlotOutlet>
    </div>
</template>
