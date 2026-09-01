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
const paginationType = computed(
    () => resource.value.options.paginationType ?? "full",
);
const currentPage = computed(() => resource.value.results.currentPage ?? 1);
const hasPreviousPage = computed(
    () =>
        resource.value.results.hasPreviousPage ??
        (resource.value.results.currentPage ?? 1) > 1,
);
const hasNextPage = computed(
    () =>
        resource.value.results.hasNextPage ??
        (resource.value.results.lastPage !== null &&
            (resource.value.results.currentPage ?? 1) <
                resource.value.results.lastPage),
);
const selectedRowsLabel = computed(() => {
    const count = actions.selectedCount.value;

    if (count === 0) return i18n.t("noRowsSelected");
    if (count === 1) return i18n.t("oneRowSelected");

    return i18n.t("manyRowsSelected", { count });
});

function previousPage(): void {
    if (paginationType.value === "cursor") {
        table.setCursor(resource.value.results.previousCursor ?? null);
        return;
    }

    table.setPage(currentPage.value - 1);
}

function nextPage(): void {
    if (paginationType.value === "cursor") {
        table.setCursor(resource.value.results.nextCursor ?? null);
        return;
    }

    table.setPage(currentPage.value + 1);
}
</script>

<template>
    <div
        class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between"
        :data-pagination-type="paginationType"
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

            <span
                v-if="paginationType === 'full'"
                class="ms-2 text-sm whitespace-nowrap"
            >
                {{
                    i18n.t("pageOf", {
                        page: currentPage,
                        pages: resource.results.lastPage ?? 1,
                    })
                }}
            </span>
            <span
                v-else-if="paginationType === 'simple'"
                class="ms-2 text-sm whitespace-nowrap"
            >
                {{ i18n.t("page", { page: currentPage }) }}
            </span>

            <UiButton
                v-if="paginationType === 'full'"
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('firstPage')"
                :disabled="!hasPreviousPage"
                @click="table.setPage(1)"
            >
                <ChevronsLeft aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('previousPage')"
                :disabled="!hasPreviousPage"
                @click="previousPage"
            >
                <ChevronLeft aria-hidden="true" />
            </UiButton>
            <UiButton
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('nextPage')"
                :disabled="!hasNextPage"
                @click="nextPage"
            >
                <ChevronRight aria-hidden="true" />
            </UiButton>
            <UiButton
                v-if="paginationType === 'full'"
                variant="outline"
                size="icon-sm"
                :aria-label="i18n.t('lastPage')"
                :disabled="!hasNextPage"
                @click="table.setPage(resource.results.lastPage ?? 1)"
            >
                <ChevronsRight aria-hidden="true" />
            </UiButton>
        </div>
    </div>
</template>
