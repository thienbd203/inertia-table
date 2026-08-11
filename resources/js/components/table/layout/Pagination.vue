<script setup lang="ts">
import {
    ChevronsLeft,
    ChevronsRight,
    ChevronLeft,
    ChevronRight,
} from "@lucide/vue";
import { UiButton } from "@/components/ui/button";
import {
    NativeSelect,
    NativeSelectOption,
} from "@/components/ui/native-select";
import { useTableContext } from "@/context/tableContext";

const { actions, resource, table } = useTableContext();
</script>

<template>
    <div
        class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <span class="text-sm">
            {{ actions.selectedItems.value.length || "No" }}
            {{ actions.selectedItems.value.length === 1 ? "row" : "rows" }}
            selected
        </span>

        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <span class="text-muted-foreground text-sm whitespace-nowrap">
                Rows per page
            </span>
            <NativeSelect
                wrapper-class="w-20 shrink-0"
                :model-value="String(resource.state.perPage)"
                @update:model-value="table.setPerPage(Number($event))"
            >
                <NativeSelectOption
                    v-for="perPage in resource.options.perPage"
                    :key="perPage"
                    :value="String(perPage)"
                >
                    {{ perPage }}
                </NativeSelectOption>
            </NativeSelect>

            <span class="ms-2 text-sm whitespace-nowrap">
                Page {{ resource.results.currentPage }} of
                {{ resource.results.lastPage }}
            </span>

            <UiButton
                variant="outline"
                size="icon-sm"
                aria-label="First page"
                :disabled="resource.results.currentPage <= 1"
                @click="table.setPage(1)"
            >
                <ChevronsLeft aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                aria-label="Previous page"
                :disabled="resource.results.currentPage <= 1"
                @click="table.setPage(resource.results.currentPage - 1)"
            >
                <ChevronLeft aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                aria-label="Next page"
                :disabled="
                    resource.results.currentPage >= resource.results.lastPage
                "
                @click="table.setPage(resource.results.currentPage + 1)"
            >
                <ChevronRight aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                aria-label="Last page"
                :disabled="
                    resource.results.currentPage >= resource.results.lastPage
                "
                @click="table.setPage(resource.results.lastPage)"
            >
                <ChevronsRight aria-hidden="true" />
            </UiButton>
        </div>
    </div>
</template>
