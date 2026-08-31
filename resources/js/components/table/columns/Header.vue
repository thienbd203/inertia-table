<script setup lang="ts">
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
    Pin,
    PinOff,
} from "@lucide/vue";
import { SlotOutlet } from "../shared";

defineProps<{ canSelect: boolean }>();
const { resource, table, actions, sticky, i18n } = useTableContext();

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
                :class="[
                    column.headerClass,
                    alignmentClass(column.alignment),
                    {
                        'tb-sticky-cell': sticky.pinSide(column.attribute),
                        'tb-sticky-header-cell': resource.options.stickyHeader,
                    },
                ]"
                :style="sticky.style(column.attribute)"
                :title="column.tooltip ?? undefined"
                :ref="
                    (element) =>
                        sticky.registerHeaderCell(column.attribute, element)
                "
            >
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
                                        table.setSort(column.attribute, 'asc')
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
                                        table.setSort(column.attribute, 'desc')
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
                                @select="table.toggleColumn(column.attribute)"
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
                                    table.togglePinnedColumn(column.attribute)
                                "
                            >
                                <PinOff
                                    v-if="table.columnPinSide(column.attribute)"
                                    class="size-4"
                                />
                                <Pin v-else class="size-4" />
                                {{
                                    i18n.t(
                                        table.columnPinSide(column.attribute)
                                            ? "unpinColumn"
                                            : "pinColumn",
                                    )
                                }}
                            </UiDropdownMenuItem>
                        </UiDropdownMenuContent>
                    </UiDropdownMenu>
                    <span v-else>{{ column.header }}</span>
                </SlotOutlet>
            </UiTableHead>
        </UiTableRow>
    </UiTableHeader>
</template>
