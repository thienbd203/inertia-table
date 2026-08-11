<script setup lang="ts">
import { UiButton } from "@/components/ui/button";
import {
    NativeSelect,
    NativeSelectOption,
} from "@/components/ui/native-select";
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
        <NativeSelect
            :model-value="String(resource.state.perPage)"
            @update:model-value="table.setPerPage(Number($event))"
        >
            <NativeSelectOption
                v-for="perPage in resource.options.perPage"
                :key="perPage"
                :value="String(perPage)"
            >
                {{ perPage }} / page
            </NativeSelectOption>
        </NativeSelect>
    </div>
</template>
