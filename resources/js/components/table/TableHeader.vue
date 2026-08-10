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
                                variant="ghost"
                                size="sm"
                                class="tb-sort-button"
                            >
                                {{ column.header }}
                                <ChevronsUpDown
                                    v-if="column.sortable"
                                    class="size-3.5 text-muted-foreground"
                                />
                            </UiButton>
                        </UiDropdownMenuTrigger>
                        <UiDropdownMenuContent align="start">
                            <template v-if="column.sortable">
                                <UiDropdownMenuItem
                                    @select="
                                        table.setSort(column.attribute, 'asc')
                                    "
                                >
                                    <ArrowUp class="size-4" />
                                    Asc
                                </UiDropdownMenuItem>
                                <UiDropdownMenuItem
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
