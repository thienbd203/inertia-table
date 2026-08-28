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
import { computed } from "vue";

const { actions, resource, table, i18n } = useTableContext();
const selectedRowsLabel = computed(() => {
    const count = actions.selectedCount.value;

    if (count === 0) return i18n.t("noRowsSelected");
    if (count === 1) return i18n.t("oneRowSelected");

    return i18n.t("manyRowsSelected", { count });
});
</script>

<template>
    <div
        class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <span class="text-sm">{{ selectedRowsLabel }}</span>

        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <span class="text-muted-foreground text-sm whitespace-nowrap">
                {{ i18n.t("rowsPerPage") }}
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
                {{
                    i18n.t("pageOf", {
                        page: resource.results.currentPage,
                        pages: resource.results.lastPage,
                    })
                }}
            </span>

            <UiButton
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('firstPage')"
                :disabled="resource.results.currentPage <= 1"
                @click="table.setPage(1)"
            >
                <ChevronsLeft aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('previousPage')"
                :disabled="resource.results.currentPage <= 1"
                @click="table.setPage(resource.results.currentPage - 1)"
            >
                <ChevronLeft aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('nextPage')"
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
                :aria-label="i18n.t('lastPage')"
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
