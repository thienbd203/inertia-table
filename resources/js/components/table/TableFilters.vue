<script setup lang="ts">
import { useTableContext } from "../../context/tableContext";
import type { TableFilter } from "../../types";
import { UiButton } from "../ui/button";
import { UiInput } from "../ui/input";
import { UiSelect } from "../ui/select";
import SlotOutlet from "./SlotOutlet";

const { resource, table } = useTableContext();

function selectOptions(filter: TableFilter) {
    return [
        { label: "All", value: "__all__" },
        ...filter.options.map((option) => ({
            label: option.label,
            value: String(option.value),
        })),
    ];
}

function filterValue(filter: TableFilter): string {
    const value = resource.value.state.filters[filter.attribute]?.value;
    return value === undefined || value === null ? "__all__" : String(value);
}

function updateSelectFilter(filter: TableFilter, value: string) {
    table.setFilter(filter.attribute, value === "__all__" ? null : value);
}
</script>

<template>
    <div v-if="resource.filters.length" class="tb-filters">
        <label
            v-for="filter in resource.filters"
            :key="filter.attribute"
            class="tb-filter"
        >
            <span>{{ filter.label }}</span>
            <SlotOutlet
                :name="`filter(${filter.attribute})`"
                :slot-props="{
                    filter,
                    state: resource.state.filters[filter.attribute],
                    update: (value: unknown, clause?: string) =>
                        table.setFilter(filter.attribute, value, clause),
                }"
            >
                <UiSelect
                    v-if="filter.type === 'select' || filter.type === 'boolean'"
                    :model-value="filterValue(filter)"
                    :options="
                        filter.type === 'boolean'
                            ? [
                                  { label: 'All', value: '__all__' },
                                  { label: 'Yes', value: '1' },
                                  { label: 'No', value: '0' },
                              ]
                            : selectOptions(filter)
                    "
                    @update:model-value="updateSelectFilter(filter, $event)"
                />
                <UiInput
                    v-else
                    :model-value="
                        String(
                            resource.state.filters[filter.attribute]?.value ??
                                '',
                        )
                    "
                    @change="
                        table.setFilter(
                            filter.attribute,
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </SlotOutlet>
        </label>

        <UiButton
            v-if="table.hasActiveState.value"
            variant="ghost"
            @click="table.clearAll"
        >
            Clear
        </UiButton>
    </div>
</template>
