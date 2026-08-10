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
import { ArrowDown, ArrowUp, ChevronsUpDown, EyeOff } from "@lucide/vue";
import SlotOutlet from "./SlotOutlet";

defineProps<{ canSelect: boolean }>();
const { resource, table, actions } = useTableContext();

function sortDirection(attribute: string): "asc" | "desc" | null {
    if (resource.value.state.sort === attribute) return "asc";
    if (resource.value.state.sort === `-${attribute}`) return "desc";

    return null;
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
                    <UiDropdownMenu v-if="column.sortable || column.toggleable">
                        <UiDropdownMenuTrigger>
                            <UiButton
                                :variant="
                                    sortDirection(column.attribute)
                                        ? 'secondary'
                                        : 'ghost'
                                "
                                size="sm"
                                class="tb-sort-button"
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
                                    :class="
                                        sortDirection(column.attribute) ===
                                        'asc'
                                            ? 'bg-accent text-accent-foreground'
                                            : undefined
                                    "
                                    @select="
                                        table.setSort(column.attribute, 'asc')
                                    "
                                >
                                    <ArrowUp class="size-4" />
                                    Asc
                                </UiDropdownMenuItem>
                                <UiDropdownMenuItem
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
                                    Desc
                                </UiDropdownMenuItem>
                            </template>
                            <UiDropdownMenuSeparator
                                v-if="column.sortable && column.toggleable"
                            />
                            <UiDropdownMenuItem
                                v-if="column.toggleable"
                                @select="table.toggleColumn(column.attribute)"
                            >
                                <EyeOff class="size-4" />
                                Hide
                            </UiDropdownMenuItem>
                        </UiDropdownMenuContent>
                    </UiDropdownMenu>
                    <span v-else>{{ column.header }}</span>
                </SlotOutlet>
            </UiTableHead>
        </UiTableRow>
    </UiTableHeader>
</template>
