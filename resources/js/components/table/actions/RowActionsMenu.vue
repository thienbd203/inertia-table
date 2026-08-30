<script setup lang="ts">
import { Ellipsis } from "@lucide/vue";
import { UiButton } from "@/components/ui/button";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTableContext } from "@/context/tableContext";
import type { TableItem } from "@/types";
import { SlotOutlet } from "../shared";
import ActionMenuItem from "./ActionMenuItem.vue";

defineProps<{ item: TableItem }>();
const { actions, i18n } = useTableContext();
</script>

<template>
    <UiDropdownMenu v-if="actions.rowActionsFor(item).length">
        <UiDropdownMenuTrigger as-child>
            <UiButton
                variant="ghost"
                size="icon-sm"
                :aria-label="i18n.t('rowActions')"
            >
                <Ellipsis aria-hidden="true" />
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent align="end">
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
                    execute: () => actions.performAction(action, item),
                }"
            >
                <ActionMenuItem :action="action" :item="item" />
            </SlotOutlet>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
