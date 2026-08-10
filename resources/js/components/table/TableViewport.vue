<script setup lang="ts">
import { computed } from "vue";
import { useTableContext } from "../../context/tableContext";
import { UiTable } from "../ui/table";
import SlotOutlet from "./SlotOutlet";
import TableBody from "./TableBody.vue";
import TableHeader from "./TableHeader.vue";

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
                    <TableHeader :can-select="canSelect" />
                </SlotOutlet>
                <SlotOutlet name="tbody">
                    <TableBody :can-select="canSelect" />
                </SlotOutlet>
            </UiTable>
        </SlotOutlet>

        <SlotOutlet v-if="table.isNavigating.value" name="loading">
            <div class="tb-loading" role="status">Loading…</div>
        </SlotOutlet>
    </div>
</template>
