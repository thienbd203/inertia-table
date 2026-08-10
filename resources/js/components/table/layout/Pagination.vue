<script setup lang="ts">
import { UiButton } from "@/components/ui/button";
import { UiSelect } from "@/components/ui/select";
import { useTableContext } from "@/context/tableContext";

const { resource, table } = useTableContext();
</script>

<template>
    <div class="tb-footer">
        <span>
            {{ resource.results.from }}–{{ resource.results.to }} of
            {{ resource.results.total }}
        </span>
        <div class="tb-pagination">
            <UiButton
                variant="outline"
                :disabled="resource.results.currentPage <= 1"
                @click="table.setPage(resource.results.currentPage - 1)"
            >
                Previous
            </UiButton>
            <span>
                {{ resource.results.currentPage }} /
                {{ resource.results.lastPage }}
            </span>
            <UiButton
                variant="outline"
                :disabled="
                    resource.results.currentPage >= resource.results.lastPage
                "
                @click="table.setPage(resource.results.currentPage + 1)"
            >
                Next
            </UiButton>
        </div>
        <UiSelect
            :model-value="String(resource.state.perPage)"
            :options="
                resource.options.perPage.map((value) => ({
                    value: String(value),
                    label: `${value} / page`,
                }))
            "
            @update:model-value="table.setPerPage(Number($event))"
        />
    </div>
</template>
