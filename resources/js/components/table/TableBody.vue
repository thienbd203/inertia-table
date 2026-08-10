<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useTableContext } from "../../context/tableContext";
import { cellUrl, cellValue, displayValue } from "../../helpers/cells";
import { UiCheckbox } from "../ui/checkbox";
import { UiTableBody, UiTableCell, UiTableRow } from "../ui/table";
import SlotOutlet from "./SlotOutlet";
import TableActionButton from "./TableActionButton.vue";

defineProps<{ canSelect: boolean }>();
const { resource, table, actions } = useTableContext();
</script>

<template>
    <UiTableBody>
        <UiTableRow v-if="resource.results.data.length === 0">
            <UiTableCell
                :colspan="
                    table.visibleColumns.value.length + (canSelect ? 1 : 0)
                "
                class="tb-empty-state"
            >
                <SlotOutlet name="emptyState">No results found.</SlotOutlet>
            </UiTableCell>
        </UiTableRow>
        <UiTableRow
            v-for="(item, index) in resource.results.data"
            v-else
            :key="String(item.id ?? index)"
            :data-selected="actions.isItemSelected(item, index) || undefined"
        >
            <UiTableCell v-if="canSelect" class="tb-selection-cell">
                <UiCheckbox
                    aria-label="Select row"
                    :model-value="actions.isItemSelected(item, index)"
                    @update:model-value="actions.toggleItem(item, index)"
                />
            </UiTableCell>
            <UiTableCell
                v-for="column in table.visibleColumns.value"
                :key="column.attribute"
                :data-alignment="column.alignment"
            >
                <SlotOutlet
                    :name="`cell(${column.attribute})`"
                    :slot-props="{
                        item,
                        value: cellValue(item, column.attribute),
                        column,
                    }"
                >
                    <div v-if="column.type === 'action'" class="tb-row-actions">
                        <SlotOutlet
                            v-for="action in actions.rowActionsFor(item)"
                            :key="action.key"
                            :name="`action(${action.key})`"
                            :slot-props="{
                                action,
                                item,
                                selectedItems: actions.selectedItems.value,
                                execute: () =>
                                    actions.performAction(action, item),
                            }"
                        >
                            <TableActionButton :action="action" :item="item" />
                        </SlotOutlet>
                    </div>
                    <Link
                        v-else-if="cellUrl(item, column.attribute)"
                        :href="cellUrl(item, column.attribute) ?? '#'"
                        class="tb-cell-link"
                    >
                        {{ displayValue(item, column) }}
                    </Link>
                    <template v-else>{{ displayValue(item, column) }}</template>
                </SlotOutlet>
            </UiTableCell>
        </UiTableRow>
    </UiTableBody>
</template>
