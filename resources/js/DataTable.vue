<script setup lang="ts" generic="T extends TableItem">
import { toRef } from "vue";
import {
    UiButton,
    UiCheckbox,
    UiInput,
    UiSelect,
    UiTable,
    UiTableBody,
    UiTableCell,
    UiTableHead,
    UiTableHeader,
    UiTableRow,
} from "./shadcn";
import type { TableFilter, TableItem, TableResource } from "./types";
import { useDataTable } from "./useDataTable";

const props = withDefaults(
    defineProps<{
        resource: TableResource<T>;
        selectable?: boolean;
        searchPlaceholder?: string;
    }>(),
    {
        selectable: false,
        searchPlaceholder: "Search…",
    },
);

const table = useDataTable(toRef(props, "resource"));

function filterOptions(filter: TableFilter): Array<[string, string]> {
    if (Array.isArray(filter.options)) {
        return filter.options.map((label) => [label, label]);
    }

    return Object.entries(filter.options ?? {});
}

function selectOptions(filter: TableFilter) {
    return [
        { label: "All", value: "__all__" },
        ...filterOptions(filter).map(([value, label]) => ({ value, label })),
    ];
}

function setSelectFilter(filter: TableFilter, value: string) {
    table.setFilter(filter.attribute, value === "__all__" ? null : value);
}

function onFilterInput(filter: TableFilter, event: Event) {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    let value: unknown = target.value;

    if (filter.type === "boolean") {
        value = value === "" ? null : value === "true";
    }

    table.setFilter(filter.attribute, value);
}

function sortIndicator(attribute: string): string {
    if (props.resource.state.sort === attribute) {
        return " ↑";
    }

    if (props.resource.state.sort === `-${attribute}`) {
        return " ↓";
    }

    return "";
}

function cellValue(item: T, attribute: string): unknown {
    return (item as Record<string, unknown>)[attribute];
}
</script>

<template>
    <div class="toolbelt-table" :aria-busy="table.isNavigating.value">
        <div class="toolbelt-table__toolbar">
            <slot name="before-search" :table="table" />

            <slot
                v-if="resource.columns.some((column) => column.searchable)"
                name="search"
                :value="table.search.value"
                :update="table.setSearch"
                :table="table"
            >
                <UiInput
                    type="search"
                    :model-value="table.search.value"
                    :placeholder="searchPlaceholder"
                    @update:model-value="table.setSearch(String($event))"
                />
            </slot>

            <div
                v-if="resource.filters.length > 0"
                class="toolbelt-table__filters"
            >
                <label
                    v-for="filter in resource.filters"
                    :key="filter.attribute"
                    class="toolbelt-table__filter"
                >
                    <slot
                        :name="`filter(${filter.attribute})`"
                        :filter="filter"
                        :value="resource.state.filters[filter.attribute]"
                        :update="
                            (value: unknown) =>
                                table.setFilter(filter.attribute, value)
                        "
                        :table="table"
                    >
                        <span>{{ filter.label }}</span>

                        <UiSelect
                            v-if="filter.type === 'select'"
                            :model-value="
                                String(
                                    resource.state.filters[filter.attribute] ??
                                        '__all__',
                                )
                            "
                            :options="selectOptions(filter)"
                            :placeholder="filter.label"
                            @update:model-value="
                                setSelectFilter(filter, $event)
                            "
                        />

                        <select
                            v-else-if="filter.type === 'boolean'"
                            :value="
                                resource.state.filters[filter.attribute] ===
                                undefined
                                    ? ''
                                    : String(
                                          resource.state.filters[
                                              filter.attribute
                                          ],
                                      )
                            "
                            @change="onFilterInput(filter, $event)"
                        >
                            <option value="">All</option>
                            <option value="true">Yes</option>
                            <option value="false">No</option>
                        </select>

                        <input
                            v-else
                            type="text"
                            :value="
                                resource.state.filters[filter.attribute] ?? ''
                            "
                            @change="onFilterInput(filter, $event)"
                        />
                    </slot>
                </label>

                <slot
                    v-if="table.hasActiveFilters.value"
                    name="clear-filters"
                    :clear="table.clearAll"
                    :table="table"
                >
                    <UiButton
                        type="button"
                        variant="ghost"
                        @click="table.clearAll"
                    >
                        Clear filters
                    </UiButton>
                </slot>
            </div>

            <slot name="after-filters" :table="table" />
        </div>

        <div class="toolbelt-table__viewport">
            <UiTable class="toolbelt-table__element">
                <UiTableHeader>
                    <UiTableRow>
                        <UiTableHead
                            v-if="selectable"
                            class="toolbelt-table__selection"
                        >
                            <UiCheckbox
                                aria-label="Select current page"
                                :model-value="table.allPageSelected.value"
                                @update:model-value="table.togglePage"
                            />
                        </UiTableHead>
                        <UiTableHead
                            v-for="column in resource.columns"
                            :key="column.attribute"
                        >
                            <slot
                                :name="`header(${column.attribute})`"
                                :column="column"
                                :table="table"
                            >
                                <UiButton
                                    v-if="column.sortable"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="toolbelt-table__sort"
                                    @click="table.setSort(column.attribute)"
                                >
                                    {{ column.label
                                    }}{{ sortIndicator(column.attribute) }}
                                </UiButton>
                                <span v-else>{{ column.label }}</span>
                            </slot>
                        </UiTableHead>
                    </UiTableRow>
                </UiTableHeader>
                <UiTableBody>
                    <UiTableRow v-if="resource.results.data.length === 0">
                        <UiTableCell
                            :colspan="
                                resource.columns.length + (selectable ? 1 : 0)
                            "
                            class="toolbelt-table__empty"
                        >
                            <slot name="empty" :table="table">
                                No results found.
                            </slot>
                        </UiTableCell>
                    </UiTableRow>
                    <UiTableRow
                        v-for="(item, index) in resource.results.data"
                        v-else
                        :key="String(item.id ?? index)"
                        :data-selected="
                            table.isRowSelected(item, index) || null
                        "
                    >
                        <UiTableCell
                            v-if="selectable"
                            class="toolbelt-table__selection"
                        >
                            <UiCheckbox
                                aria-label="Select row"
                                :model-value="table.isRowSelected(item, index)"
                                @update:model-value="
                                    table.toggleRow(item, index)
                                "
                            />
                        </UiTableCell>
                        <UiTableCell
                            v-for="column in resource.columns"
                            :key="column.attribute"
                        >
                            <slot
                                :name="`cell(${column.attribute})`"
                                :item="item"
                                :value="cellValue(item, column.attribute)"
                                :column="column"
                                :table="table"
                            >
                                {{ cellValue(item, column.attribute) }}
                            </slot>
                        </UiTableCell>
                    </UiTableRow>
                </UiTableBody>
            </UiTable>
        </div>

        <div v-if="resource.results.total > 0" class="toolbelt-table__footer">
            <slot name="before-pagination" :table="table">
                <span>
                    {{ resource.results.from }}–{{ resource.results.to }} of
                    {{ resource.results.total }}
                </span>
            </slot>

            <div class="toolbelt-table__pagination">
                <UiButton
                    type="button"
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
                    type="button"
                    variant="outline"
                    :disabled="
                        resource.results.currentPage >=
                        resource.results.lastPage
                    "
                    @click="table.setPage(resource.results.currentPage + 1)"
                >
                    Next
                </UiButton>
            </div>

            <label>
                <span class="toolbelt-sr-only">Rows per page</span>
                <UiSelect
                    :model-value="String(resource.state.perPage)"
                    :options="
                        resource.perPageOptions.map((option) => ({
                            value: String(option),
                            label: `${option} / page`,
                        }))
                    "
                    @update:model-value="table.setPerPage(Number($event))"
                />
            </label>
        </div>
    </div>
</template>

<style>
.toolbelt-table {
    display: grid;
    gap: 1rem;
}

.toolbelt-table__toolbar,
.toolbelt-table__filters,
.toolbelt-table__footer,
.toolbelt-table__pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.toolbelt-table__filter {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.toolbelt-table__viewport {
    overflow-x: auto;
}

.toolbelt-table__element {
    width: 100%;
    border-collapse: collapse;
    caption-side: bottom;
    font-size: 0.875rem;
}

.toolbelt-table__element th,
.toolbelt-table__element td {
    height: 3rem;
    padding: 0.625rem 1rem;
    text-align: left;
    border-bottom: 1px solid currentColor;
    border-color: color-mix(in srgb, currentColor 15%, transparent);
}

.toolbelt-table__element th {
    color: var(--muted-foreground, #71717a);
    font-weight: 500;
}

.toolbelt-table__element tr {
    transition: background-color 150ms;
}

.toolbelt-table__element tbody tr:hover,
.toolbelt-table__element tr[data-selected] {
    background: var(--muted, #f4f4f5);
}

.toolbelt-table [data-slot="button"] {
    display: inline-flex;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding-inline: 1rem;
    color: var(--primary-foreground, white);
    font: inherit;
    font-weight: 500;
    white-space: nowrap;
    background: var(--primary, #18181b);
    border: 1px solid transparent;
    border-radius: calc(var(--radius, 0.625rem) - 2px);
    cursor: pointer;
}

.toolbelt-table [data-slot="button"][data-size="sm"] {
    height: 2rem;
    padding-inline: 0.75rem;
}

.toolbelt-table [data-slot="button"][data-variant="outline"] {
    color: var(--foreground, #18181b);
    background: var(--background, white);
    border-color: var(--border, #e4e4e7);
}

.toolbelt-table [data-slot="button"][data-variant="ghost"] {
    color: var(--foreground, #18181b);
    background: transparent;
}

.toolbelt-table [data-slot="button"]:hover {
    filter: brightness(0.96);
}

.toolbelt-table [data-slot="button"]:disabled {
    pointer-events: none;
    opacity: 0.5;
}

.toolbelt-table [data-slot="input"],
.toolbelt-table [data-slot="select-trigger"],
.toolbelt-table select,
.toolbelt-table input[type="text"] {
    height: 2.25rem;
    min-width: 10rem;
    padding-inline: 0.75rem;
    color: var(--foreground, #18181b);
    font: inherit;
    background: transparent;
    border: 1px solid var(--input, #d4d4d8);
    border-radius: calc(var(--radius, 0.625rem) - 2px);
    box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
    outline: none;
}

.toolbelt-table [data-slot="input"]:focus,
.toolbelt-table [data-slot="select-trigger"]:focus {
    border-color: var(--ring, #a1a1aa);
    box-shadow: 0 0 0 3px
        color-mix(in srgb, var(--ring, #a1a1aa) 35%, transparent);
}

.toolbelt-table [data-slot="checkbox"] {
    display: inline-grid;
    width: 1rem;
    height: 1rem;
    padding: 0;
    place-content: center;
    color: var(--primary-foreground, white);
    background: transparent;
    border: 1px solid var(--input, #d4d4d8);
    border-radius: 0.25rem;
}

.toolbelt-table [data-slot="checkbox"][data-state="checked"] {
    background: var(--primary, #18181b);
    border-color: var(--primary, #18181b);
}

[data-slot="select-content"] {
    z-index: 50;
    min-width: var(--reka-select-trigger-width);
    padding: 0.25rem;
    overflow: hidden;
    color: var(--popover-foreground, #18181b);
    background: var(--popover, white);
    border: 1px solid var(--border, #e4e4e7);
    border-radius: calc(var(--radius, 0.625rem) - 2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 10%);
}

[data-slot="select-item"] {
    position: relative;
    display: flex;
    min-height: 2rem;
    align-items: center;
    padding: 0.375rem 2rem 0.375rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.25rem;
    outline: none;
    cursor: default;
}

[data-slot="select-item"][data-highlighted] {
    background: var(--accent, #f4f4f5);
}

[data-slot="select-item-indicator"] {
    position: absolute;
    right: 0.5rem;
}

.toolbelt-table__selection {
    width: 1%;
    white-space: nowrap;
}

.toolbelt-table__sort {
    padding: 0;
    color: inherit;
    font: inherit;
    background: none;
    border: 0;
    cursor: pointer;
}

.toolbelt-table__empty {
    padding-block: 2rem !important;
    text-align: center !important;
}

.toolbelt-table__footer {
    justify-content: space-between;
}

.toolbelt-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
