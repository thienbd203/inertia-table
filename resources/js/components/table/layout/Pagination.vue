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
const pageNumbers = computed(() => {
    if (paginationType.value !== "full") return [];

    const lastPage = Math.max(resource.value.results.lastPage ?? 1, 1);
    const windowSize = 5;
    const activePage = Math.min(Math.max(currentPage.value, 1), lastPage);
    let startPage = Math.max(activePage - Math.floor(windowSize / 2), 1);
    const endPage = Math.min(startPage + windowSize - 1, lastPage);

    startPage = Math.max(endPage - windowSize + 1, 1);

    return Array.from(
        { length: endPage - startPage + 1 },
        (_, index) => startPage + index,
    );
});
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

function goToPage(page: number): void {
    if (page === currentPage.value) return;

    table.setPage(page);
}
</script>

<template>
    <div
        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 py-4 sm:flex sm:flex-wrap sm:gap-2"
        :data-pagination-type="paginationType"
    >
        <span class="truncate text-sm sm:me-auto">
            {{ selectedRowsLabel }}
        </span>

        <div class="flex items-center gap-2">
            <span
                class="text-muted-foreground hidden text-sm whitespace-nowrap sm:inline"
            >
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
        </div>

        <div
            class="col-span-2 flex w-full items-center justify-between gap-2 sm:col-auto sm:w-auto sm:justify-end"
        >
            <span
                v-if="paginationType === 'full'"
                class="text-sm whitespace-nowrap md:hidden"
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
                class="text-sm whitespace-nowrap"
            >
                {{ i18n.t("page", { page: currentPage }) }}
            </span>

            <div class="ms-auto flex items-center gap-2">
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
                <nav
                    v-if="paginationType === 'full'"
                    class="hidden items-center gap-1 md:flex"
                    :aria-label="i18n.t('pagination')"
                >
                    <UiButton
                        v-for="page in pageNumbers"
                        :key="page"
                        :variant="page === currentPage ? 'default' : 'outline'"
                        size="icon-sm"
                        :aria-label="i18n.t('goToPage', { page })"
                        :aria-current="
                            page === currentPage ? 'page' : undefined
                        "
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </UiButton>
                </nav>
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
    </div>
</template>
