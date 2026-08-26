<script setup lang="ts">
import { UiButton } from "@/components/ui/button";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuLabel,
    UiDropdownMenuSeparator,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTableContext } from "@/context/tableContext";
import { SlotOutlet } from "../shared";
import ActionMenuItem from "./ActionMenuItem.vue";
import { Wrench } from "@lucide/vue";

const { actions, i18n } = useTableContext();
</script>

<template>
    <UiDropdownMenu v-if="actions.bulkActions.value.length">
        <UiDropdownMenuTrigger>
            <UiButton variant="outline">
                <Wrench class="h-4 w-4" />{{ i18n.t("actions") }}</UiButton
            >
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent>
            <UiDropdownMenuLabel>
                {{ i18n.t("bulkActions") }}
            </UiDropdownMenuLabel>
            <UiDropdownMenuSeparator />
            <SlotOutlet
                v-for="action in actions.bulkActions.value"
                :key="action.key"
                :name="`action(${action.key})`"
                :slot-props="{
                    action,
                    item: null,
                    selectedItems: actions.selectedItems.value,
                    execute: () => actions.performAction(action),
                }"
            >
                <ActionMenuItem :action="action" />
            </SlotOutlet>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
