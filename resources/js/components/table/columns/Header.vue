<script setup lang="ts">
import { ref } from "vue";
import { UiButton } from "@/components/ui/button";
import { UiCheckbox } from "@/components/ui/checkbox";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuItem,
    UiDropdownMenuSeparator,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { UiTableHead, UiTableHeader, UiTableRow } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import {
    ArrowDown,
    ArrowUp,
    ChevronsUpDown,
    EyeOff,
    GripVertical,
    Pin,
    PinOff,
} from "@lucide/vue";
import ColumnResizeHandle from "./ColumnResizeHandle.vue";
import { SlotOutlet } from "../shared";

defineProps<{ canSelect: boolean }>();
const { resource, table, actions, sticky, i18n } = useTableContext();
const draggedColumn = ref<string | null>(null);
const dropTarget = ref<string | null>(null);

function sortDirection(attribute: string): "asc" | "desc" | null {
    if (resource.value.state.sort === attribute) return "asc";
    if (resource.value.state.sort === `-${attribute}`) return "desc";

    return null;
}

function alignmentClass(alignment: "left" | "center" | "right"): string {
    return {
        left: "text-left",
        center: "text-center",
        right: "text-right",
    }[alignment];
}

function canTogglePin(attribute: string): boolean {
    const column = resource.value.columns.find(
        (candidate) => candidate.attribute === attribute,
    );

    return column?.stickable === true && column.sticky !== true;
}

function beginDrag(event: DragEvent, attribute: string) {
    draggedColumn.value = attribute;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", attribute);
    }
}

function dragOver(event: DragEvent, target: string) {
    if (!draggedColumn.value || draggedColumn.value === target) return;
    event.preventDefault();
    dropTarget.value = target;
    if (event.dataTransfer) event.dataTransfer.dropEffect = "move";
}

function dropColumn(event: DragEvent, target: string) {
    event.preventDefault();
    if (draggedColumn.value) table.swapColumns(draggedColumn.value, target);
    draggedColumn.value = null;
    dropTarget.value = null;
}

function endDrag() {
    draggedColumn.value = null;
    dropTarget.value = null;
}

function touchMove(event: PointerEvent) {
    if (event.pointerType !== "touch") return;
    const element = document.elementFromPoint(event.clientX, event.clientY);
    dropTarget.value =
        element?.closest<HTMLElement>("[data-column]")?.dataset.column ?? null;
}

function beginTouchReorder(event: PointerEvent, attribute: string) {
    if (event.pointerType !== "touch") return;
    dropTarget.value = attribute;
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
}

function finishTouchReorder(attribute: string) {
    if (dropTarget.value) table.swapColumns(attribute, dropTarget.value);
    dropTarget.value = null;
}

function reorderKeydown(event: KeyboardEvent, attribute: string) {
    if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;

    event.preventDefault();
    const target = event.currentTarget as HTMLElement;
    const container = target.closest("[data-slot=table-container]");
    const rtl =
        container && window.getComputedStyle(container).direction === "rtl";
    const moveEarlier = event.key === (rtl ? "ArrowRight" : "ArrowLeft");
    const direction: -1 | 1 = moveEarlier ? -1 : 1;
    table.moveColumn(attribute, direction);
}
</script>

<template>
    <UiTableHeader
        :data-sticky-header="resource.options.stickyHeader || undefined"
    >
        <UiTableRow>
            <UiTableHead
                v-if="canSelect"
                :ref="
                    (element) =>
                        sticky.registerHeaderCell(
                            sticky.selectionColumn,
                            element,
                        )
                "
                class="tb-selection-cell"
                :class="{
                    'tb-sticky-cell': sticky.selectionPinned.value,
                    'tb-sticky-header-cell': resource.options.stickyHeader,
                }"
                :data-sticky-side="
                    sticky.selectionPinned.value ? 'left' : undefined
                "
                :style="sticky.style(sticky.selectionColumn)"
            >
                <UiCheckbox
                    :aria-label="
                        i18n.t('selectAllMatching', {
                            count: actions.selectableTotal.value,
                        })
                    "
                    :title="
                        i18n.t('selectAllMatching', {
                            count: actions.selectableTotal.value,
                        })
                    "
                    :model-value="actions.selectionState.value"
                    :disabled="actions.selectableTotal.value === 0"
                    @update:model-value="actions.toggleAll"
                />
            </UiTableHead>
            <UiTableHead
                v-for="column in table.visibleColumns.value"
                :key="column.attribute"
                :data-column="column.attribute"
                :data-alignment="column.alignment"
                :data-sticky-side="
                    sticky.pinSide(column.attribute) ?? undefined
                "
                :data-sticky-edge="sticky.edge(column.attribute) ?? undefined"
                :data-drop-target="
                    dropTarget === column.attribute ? '' : undefined
                "
                :class="[
                    column.headerClass,
                    alignmentClass(column.alignment),
                    {
                        'tb-sticky-cell': sticky.pinSide(column.attribute),
                        'tb-sticky-header-cell': resource.options.stickyHeader,
                        'tb-resizable-column':
                            resource.options.columnResizing !== false &&
                            column.resizable,
                        'tb-reorderable-column':
                            resource.options.columnReordering !== false &&
                            column.reorderable,
                    },
                ]"
                :style="[
                    table.columnStyle(column.attribute),
                    sticky.style(column.attribute),
                ]"
                :title="column.tooltip ?? undefined"
                :ref="
                    (element) =>
                        sticky.registerHeaderCell(column.attribute, element)
                "
                @dragover="dragOver($event, column.attribute)"
                @drop="dropColumn($event, column.attribute)"
            >
                <div class="tb-column-header-content">
                    <SlotOutlet
                        :name="`header(${column.attribute})`"
                        :slot-props="{ column }"
                    >
                        <UiDropdownMenu
                            v-if="
                                column.sortable ||
                                column.toggleable ||
                                canTogglePin(column.attribute)
                            "
                        >
                            <UiDropdownMenuTrigger as-child>
                                <UiButton
                                    :variant="
                                        sortDirection(column.attribute)
                                            ? 'secondary'
                                            : 'ghost'
                                    "
                                    size="sm"
                                    class="-ms-3 tb-sort-button font-semibold"
                                    :data-active="
                                        sortDirection(column.attribute)
                                            ? ''
                                            : undefined
                                    "
                                >
                                    {{ column.header }}
                                    <ArrowUp
                                        v-if="
                                            sortDirection(column.attribute) ===
                                            'asc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            sortDirection(column.attribute) ===
                                            'desc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ChevronsUpDown
                                        v-else-if="column.sortable"
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </UiButton>
                            </UiDropdownMenuTrigger>
                            <UiDropdownMenuContent align="start">
                                <template v-if="column.sortable">
                                    <UiDropdownMenuItem
                                        class="font-medium"
                                        :class="
                                            sortDirection(column.attribute) ===
                                            'asc'
                                                ? 'bg-accent text-accent-foreground font-medium'
                                                : undefined
                                        "
                                        @select="
                                            table.setSort(
                                                column.attribute,
                                                'asc',
                                            )
                                        "
                                    >
                                        <ArrowUp class="size-4" />
                                        {{ i18n.t("ascending") }}
                                    </UiDropdownMenuItem>
                                    <UiDropdownMenuItem
                                        class="font-medium"
                                        :class="
                                            sortDirection(column.attribute) ===
                                            'desc'
                                                ? 'bg-accent text-accent-foreground'
                                                : undefined
                                        "
                                        @select="
                                            table.setSort(
                                                column.attribute,
                                                'desc',
                                            )
                                        "
                                    >
                                        <ArrowDown class="size-4" />
                                        {{ i18n.t("descending") }}
                                    </UiDropdownMenuItem>
                                </template>
                                <UiDropdownMenuSeparator
                                    v-if="
                                        column.sortable &&
                                        (column.toggleable ||
                                            canTogglePin(column.attribute))
                                    "
                                />
                                <UiDropdownMenuItem
                                    v-if="column.toggleable"
                                    class="font-medium"
                                    @select="
                                        table.toggleColumn(column.attribute)
                                    "
                                >
                                    <EyeOff class="size-4" />
                                    {{ i18n.t("hideColumn") }}
                                </UiDropdownMenuItem>
                                <UiDropdownMenuSeparator
                                    v-if="
                                        column.toggleable &&
                                        canTogglePin(column.attribute)
                                    "
                                />
                                <UiDropdownMenuItem
                                    v-if="canTogglePin(column.attribute)"
                                    class="font-medium"
                                    @select="
                                        table.togglePinnedColumn(
                                            column.attribute,
                                        )
                                    "
                                >
                                    <PinOff
                                        v-if="
                                            table.columnPinSide(
                                                column.attribute,
                                            )
                                        "
                                        class="size-4"
                                    />
                                    <Pin v-else class="size-4" />
                                    {{
                                        i18n.t(
                                            table.columnPinSide(
                                                column.attribute,
                                            )
                                                ? "unpinColumn"
                                                : "pinColumn",
                                        )
                                    }}
                                </UiDropdownMenuItem>
                            </UiDropdownMenuContent>
                        </UiDropdownMenu>
                        <span v-else class="font-semibold">
                            {{ column.header }}
                        </span>
                    </SlotOutlet>
                    <button
                        v-if="
                            resource.options.columnReordering !== false &&
                            column.reorderable
                        "
                        type="button"
                        class="tb-column-reorder-handle"
                        draggable="true"
                        :aria-label="
                            i18n.t('reorderColumn', {
                                column: column.header,
                            })
                        "
                        :title="
                            i18n.t('reorderColumn', {
                                column: column.header,
                            })
                        "
                        @dragend="endDrag"
                        @dragstart="beginDrag($event, column.attribute)"
                        @keydown="reorderKeydown($event, column.attribute)"
                        @pointerdown="
                            beginTouchReorder($event, column.attribute)
                        "
                        @pointermove="touchMove"
                        @pointerup="finishTouchReorder(column.attribute)"
                        @pointercancel="dropTarget = null"
                    >
                        <GripVertical aria-hidden="true" />
                    </button>
                </div>
                <ColumnResizeHandle
                    v-if="
                        resource.options.columnResizing !== false &&
                        column.resizable
                    "
                    :column="column"
                />
            </UiTableHead>
        </UiTableRow>
    </UiTableHeader>
</template>
