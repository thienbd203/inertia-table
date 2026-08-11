<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import { UiCheckbox } from "@/components/ui/checkbox";
import { UiTableBody, UiTableCell, UiTableRow } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import { cellUrl, cellValue, rowUrl } from "@/helpers/cells";
import type { TableItem } from "@/types";
import { ActionButton } from "../actions";
import { CellContent } from "../cells";
import { SlotOutlet } from "../shared";

defineProps<{ canSelect: boolean }>();
const { resource, table, actions } = useTableContext();

function alignmentClass(alignment: "left" | "center" | "right"): string {
    return {
        left: "text-left",
        center: "text-center",
        right: "text-right",
    }[alignment];
}

function handleRowClick(event: MouseEvent, item: TableItem) {
    const url = rowUrl(item);
    if (!url || event.defaultPrevented) return;

    const target = event.target;
    if (
        target instanceof Element &&
        target.closest("a, button, input, select, textarea, [role=checkbox]")
    ) {
        return;
    }

    router.visit(url, {
        method: "get",
        preserveScroll: true,
        preserveState: true,
    });
}
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
            :data-row-clickable="rowUrl(item) ? true : undefined"
            :class="rowUrl(item) ? 'tb-row-clickable' : undefined"
            @click="handleRowClick($event, item)"
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
                :class="[
                    column.cellClass,
                    alignmentClass(column.alignment),
                    {
                        'tb-cell-wrap': column.wrap,
                        'tb-cell-truncate': column.truncate,
                    },
                ]"
                :style="
                    column.truncate
                        ? { '--tb-line-clamp': column.truncate }
                        : undefined
                "
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
                            <ActionButton :action="action" :item="item" />
                        </SlotOutlet>
                    </div>
                    <Link
                        v-else-if="cellUrl(item, column.attribute)"
                        :href="cellUrl(item, column.attribute) ?? '#'"
                        class="tb-cell-link"
                    >
                        <CellContent :item="item" :column="column" />
                    </Link>
                    <CellContent v-else :item="item" :column="column" />
                </SlotOutlet>
            </UiTableCell>
        </UiTableRow>
    </UiTableBody>
</template>
