<script setup lang="ts">
import { UiInput } from "@/components/ui/input";
import { useTableContext } from "@/context/tableContext";
import ColumnVisibilityMenu from "./ColumnVisibilityMenu.vue";
import SlotOutlet from "./SlotOutlet";
import TableActionButton from "./TableActionButton.vue";

const { resource, table, actions, searchPlaceholder } = useTableContext();
</script>

<template>
    <div class="tb-topbar">
        <div class="tb-search-group">
            <SlotOutlet name="beforeSearch" />
            <UiInput
                v-if="resource.capabilities.searchable"
                type="search"
                :model-value="table.search.value"
                :placeholder="searchPlaceholder"
                @update:model-value="table.setSearch"
            />
            <SlotOutlet name="afterSearch" />
        </div>

        <div class="tb-action-group">
            <SlotOutlet name="beforeActions" />
            <span
                v-if="actions.selectedItems.value.length"
                class="tb-selected-count"
            >
                {{ actions.selectedItems.value.length }} selected
            </span>
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
                <TableActionButton :action="action" bulk />
            </SlotOutlet>
            <ColumnVisibilityMenu />
            <SlotOutlet name="afterActions" />
        </div>
    </div>
</template>
