<script setup lang="ts" generic="T extends TableItem">
import { toRef } from "vue";
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

            <input
                v-if="resource.columns.some((column) => column.searchable)"
                class="toolbelt-table__search"
                type="search"
                :value="table.search.value"
                :placeholder="searchPlaceholder"
                @input="
                    table.setSearch(($event.target as HTMLInputElement).value)
                "
            />

            <div
                v-if="resource.filters.length > 0"
                class="toolbelt-table__filters"
            >
                <label
                    v-for="filter in resource.filters"
                    :key="filter.attribute"
                    class="toolbelt-table__filter"
                >
                    <span>{{ filter.label }}</span>

                    <select
                        v-if="filter.type === 'select'"
                        :value="resource.state.filters[filter.attribute] ?? ''"
                        @change="onFilterInput(filter, $event)"
                    >
                        <option value="">All</option>
                        <option
                            v-for="([value, label], index) in filterOptions(
                                filter,
                            )"
                            :key="index"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>

                    <select
                        v-else-if="filter.type === 'boolean'"
                        :value="
                            resource.state.filters[filter.attribute] ===
                            undefined
                                ? ''
                                : String(
                                      resource.state.filters[filter.attribute],
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
                        :value="resource.state.filters[filter.attribute] ?? ''"
                        @change="onFilterInput(filter, $event)"
                    />
                </label>

                <button type="button" @click="table.clearFilters">
                    Clear filters
                </button>
            </div>

            <slot name="after-filters" :table="table" />
        </div>

        <div class="toolbelt-table__viewport">
            <table class="toolbelt-table__element">
                <thead>
                    <tr>
                        <th v-if="selectable" class="toolbelt-table__selection">
                            <input
                                type="checkbox"
                                aria-label="Select current page"
                                :checked="table.allPageSelected.value"
                                @change="table.togglePage"
                            />
                        </th>
                        <th
                            v-for="column in resource.columns"
                            :key="column.attribute"
                        >
                            <slot
                                :name="`header(${column.attribute})`"
                                :column="column"
                                :table="table"
                            >
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="toolbelt-table__sort"
                                    @click="table.setSort(column.attribute)"
                                >
                                    {{ column.label
                                    }}{{ sortIndicator(column.attribute) }}
                                </button>
                                <span v-else>{{ column.label }}</span>
                            </slot>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="resource.results.data.length === 0">
                        <td
                            :colspan="
                                resource.columns.length + (selectable ? 1 : 0)
                            "
                            class="toolbelt-table__empty"
                        >
                            <slot name="empty">No results found.</slot>
                        </td>
                    </tr>
                    <tr
                        v-for="(item, index) in resource.results.data"
                        v-else
                        :key="String(item.id ?? index)"
                        :data-selected="
                            table.isRowSelected(item, index) || null
                        "
                    >
                        <td v-if="selectable" class="toolbelt-table__selection">
                            <input
                                type="checkbox"
                                aria-label="Select row"
                                :checked="table.isRowSelected(item, index)"
                                @change="table.toggleRow(item, index)"
                            />
                        </td>
                        <td
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
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="resource.results.total > 0" class="toolbelt-table__footer">
            <slot name="before-pagination" :table="table">
                <span>
                    {{ resource.results.from }}–{{ resource.results.to }} of
                    {{ resource.results.total }}
                </span>
            </slot>

            <div class="toolbelt-table__pagination">
                <button
                    type="button"
                    :disabled="resource.results.currentPage <= 1"
                    @click="table.setPage(resource.results.currentPage - 1)"
                >
                    Previous
                </button>
                <span>
                    {{ resource.results.currentPage }} /
                    {{ resource.results.lastPage }}
                </span>
                <button
                    type="button"
                    :disabled="
                        resource.results.currentPage >=
                        resource.results.lastPage
                    "
                    @click="table.setPage(resource.results.currentPage + 1)"
                >
                    Next
                </button>
            </div>

            <label>
                <span class="toolbelt-sr-only">Rows per page</span>
                <select
                    :value="resource.state.perPage"
                    @change="
                        table.setPerPage(
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                >
                    <option
                        v-for="option in resource.perPageOptions"
                        :key="option"
                        :value="option"
                    >
                        {{ option }} / page
                    </option>
                </select>
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
}

.toolbelt-table__element th,
.toolbelt-table__element td {
    padding: 0.625rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid currentColor;
    border-color: color-mix(in srgb, currentColor 15%, transparent);
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
