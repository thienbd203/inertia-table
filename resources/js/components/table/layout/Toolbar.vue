<script setup lang="ts">
import { UiInput } from "@/components/ui/input";
import { useTableContext } from "@/context/tableContext";
import { ActionsMenu } from "../actions";
import { ColumnVisibilityMenu } from "../columns";
import { AddFilterMenu } from "../filters";
import { SlotOutlet } from "../shared";
import { ViewsMenu } from "../views";

const {
    resource,
    table,
    searchPlaceholder,
    activeFilterAttributes,
    addFilter,
    clearFilters,
} = useTableContext();
</script>

<template>
    <div class="tb-topbar flex items-center justify-between">
        <div class="tb-search-group">
            <SlotOutlet name="beforeSearch" />
            <UiInput
                v-if="resource.capabilities.searchable"
                type="search"
                :model-value="table.search.value"
                :placeholder="searchPlaceholder"
                @update:model-value="(value) => table.setSearch(String(value))"
                class="w-[200px]"
            />
            <SlotOutlet name="afterSearch" />
        </div>

        <div class="tb-action-group flex gap-2">
            <SlotOutlet name="beforeActions" />
            <ViewsMenu />
            <ActionsMenu />
            <AddFilterMenu
                :filters="resource.filters"
                :active-attributes="activeFilterAttributes"
                @add="addFilter"
                @clear="clearFilters"
            />
            <ColumnVisibilityMenu />
            <SlotOutlet name="afterActions" />
        </div>
    </div>
</template>
