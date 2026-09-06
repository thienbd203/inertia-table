<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import { UiCheckbox } from "@/components/ui/checkbox";
import { UiTableBody, UiTableCell, UiTableRow } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import { cellUrl, cellValue, rowUrl } from "@/helpers/cells";
import type { TableColumn, TableItem } from "@/types";
import { ActionButton, RowActionsMenu } from "../actions";
import { CellContent } from "../cells";
import { EmptyState as TableEmptyState } from "../empty";
import { SlotOutlet } from "../shared";

defineProps<{ canSelect: boolean }>();
const emit = defineEmits<{
    rowClick: [item: TableItem, column: TableColumn | null];
}>();
const { resource, table, actions, sticky, i18n } = useTableContext();

function alignmentClass(alignment: "left" | "center" | "right"): string {
    return {
        left: "text-left",
        center: "text-center",
        right: "text-right",
    }[alignment];
}

function handleSelectionClick(
    event: MouseEvent,
    item: TableItem,
    index: number,
) {
    actions.toggleItem(item, index, event.shiftKey);
}

function handleRowClick(event: MouseEvent, item: TableItem) {
    const target = event.target;
    const columnElement =
        target instanceof Element
            ? target.closest<HTMLElement>("td[data-column]")
            : null;
    const column = columnElement
        ? (table.visibleColumns.value.find(
              (candidate) =>
                  candidate.attribute === columnElement.dataset.column,
          ) ?? null)
        : null;

    emit("rowClick", item, column);

    const url = rowUrl(item);
    if (!url || url.disabled || event.defaultPrevented) return;

    if (
        target instanceof Element &&
        target.closest("a, button, input, select, textarea, [role=checkbox]")
    ) {
        return;
    }

    if (url.newTab) {
        window.open(url.url, "_blank", "noopener");

        return;
    }

    if (url.download) {
        window.location.assign(url.url);

        return;
    }

    router.visit(url.url, {
        method: "get",
        preserveScroll: url.preserveScroll,
        preserveState: url.preserveState,
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
                <SlotOutlet name="emptyState">
                    <TableEmptyState v-if="resource.emptyState" />
                    <p
                        v-else
                        class="p-8 text-center font-medium text-foreground"
                    >
                        {{ i18n.t("noResults") }}
                    </p>
                </SlotOutlet>
            </UiTableCell>
        </UiTableRow>
        <UiTableRow
            v-for="(item, index) in resource.results.data"
            v-else
            v-bind="item._table?.dataAttributes ?? {}"
            :key="String(actions.rowKey(item, index))"
            :data-selected="actions.isItemSelected(item, index) || undefined"
            :data-row-clickable="
                rowUrl(item) && !rowUrl(item)?.disabled ? true : undefined
            "
            :class="
                rowUrl(item) && !rowUrl(item)?.disabled
                    ? 'tb-row-clickable'
                    : undefined
            "
            @click="handleRowClick($event, item)"
        >
            <UiTableCell
                v-if="canSelect"
                class="tb-selection-cell"
                :class="{ 'tb-sticky-cell': sticky.selectionPinned.value }"
                :data-sticky-side="
                    sticky.selectionPinned.value ? 'left' : undefined
                "
                :style="sticky.style(sticky.selectionColumn)"
            >
                <UiCheckbox
                    :aria-label="i18n.t('selectRow')"
                    :model-value="actions.isItemSelected(item, index)"
                    :disabled="!actions.isItemSelectable(item)"
                    @click="handleSelectionClick($event, item, index)"
                />
            </UiTableCell>
            <UiTableCell
                v-for="column in table.visibleColumns.value"
                :key="column.attribute"
                :data-alignment="column.alignment"
                :data-column="column.attribute"
                :data-sticky-side="
                    sticky.pinSide(column.attribute) ?? undefined
                "
                :data-sticky-edge="sticky.edge(column.attribute) ?? undefined"
                :class="[
                    column.cellClass,
                    alignmentClass(column.alignment),
                    {
                        'tb-cell-wrap': column.wrap,
                        'tb-cell-truncate': column.truncate,
                        'tb-sticky-cell': sticky.pinSide(column.attribute),
                        'tb-column-resize-active':
                            table.resizingColumn.value === column.attribute,
                    },
                ]"
                :style="[
                    table.columnStyle(column.attribute),
                    sticky.style(column.attribute),
                    column.truncate
                        ? { '--tb-line-clamp': column.truncate }
                        : undefined,
                ]"
            >
                <SlotOutlet
                    :name="`cell(${column.attribute})`"
                    :slot-props="{
                        item,
                        value: cellValue(item, column.attribute),
                        column,
                    }"
                >
                    <div v-if="column.type === 'action'">
                        <RowActionsMenu v-if="column.asDropdown" :item="item" />
                        <template v-else>
                            <SlotOutlet
                                v-for="action in actions.rowActionsFor(item)"
                                :key="action.key"
                                :name="`action(${action.key})`"
                                :slot-props="{
                                    action,
                                    item,
                                    selectedItems: actions.selectedItems.value,
                                    selectedCount: actions.selectedCount.value,
                                    selection: actions.selection.value,
                                    execute: () =>
                                        actions.performAction(action, item),
                                }"
                            >
                                <ActionButton :action="action" :item="item" />
                            </SlotOutlet>
                        </template>
                    </div>
                    <a
                        v-else-if="
                            cellUrl(item, column.attribute)?.newTab ||
                            cellUrl(item, column.attribute)?.download
                        "
                        :href="cellUrl(item, column.attribute)?.url"
                        :target="
                            cellUrl(item, column.attribute)?.newTab
                                ? '_blank'
                                : undefined
                        "
                        :download="
                            cellUrl(item, column.attribute)?.download ||
                            undefined
                        "
                        class="tb-cell-link"
                    >
                        <CellContent :item="item" :column="column" />
                    </a>
                    <Link
                        v-else-if="
                            cellUrl(item, column.attribute) &&
                            !cellUrl(item, column.attribute)?.disabled
                        "
                        :href="cellUrl(item, column.attribute)?.url ?? '#'"
                        :preserve-scroll="
                            cellUrl(item, column.attribute)?.preserveScroll
                        "
                        :preserve-state="
                            cellUrl(item, column.attribute)?.preserveState
                        "
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
