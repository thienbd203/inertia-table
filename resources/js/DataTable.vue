<script setup lang="ts" generic="T extends TableItem">
import { Link } from "@inertiajs/vue3";
import { computed, toRef } from "vue";
import { UiButton } from "./components/ui/button";
import { UiCheckbox } from "./components/ui/checkbox";
import { UiInput } from "./components/ui/input";
import { UiSelect } from "./components/ui/select";
import {
    UiTable,
    UiTableBody,
    UiTableCell,
    UiTableHead,
    UiTableHeader,
    UiTableRow,
} from "./components/ui/table";
import type { TableFilter, TableItem, TableResource } from "./types";
import { useActions } from "./useActions";
import { useTable } from "./useTable";

const props = withDefaults(
    defineProps<{
        resource: TableResource<T>;
        searchPlaceholder?: string;
    }>(),
    { searchPlaceholder: "Search…" },
);

const table = useTable(toRef(props, "resource"));
const actions = useActions(table);
const scope = { table, actions };

const canSelect = computed(
    () =>
        props.resource.capabilities.selectable &&
        actions.bulkActions.value.length > 0,
);

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
    const value = props.resource.state.filters[filter.attribute]?.value;
    return value === undefined || value === null ? "__all__" : String(value);
}

function updateSelectFilter(filter: TableFilter, value: string) {
    table.setFilter(filter.attribute, value === "__all__" ? null : value);
}

function sortIndicator(attribute: string): string {
    if (props.resource.state.sort === attribute) return " ↑";
    if (props.resource.state.sort === `-${attribute}`) return " ↓";
    return "";
}

function cellValue(item: T, attribute: string): unknown {
    return (item as Record<string, unknown>)[attribute];
}

function cellUrl(item: T, attribute: string): string | null {
    return item._table?.columns[attribute] ?? item._table?.url ?? null;
}
</script>

<template>
    <div class="tb-wrapper" :aria-busy="table.isNavigating.value">
        <slot name="topbar" v-bind="scope">
            <div class="tb-topbar">
                <div class="tb-search-group">
                    <slot name="beforeSearch" v-bind="scope" />
                    <UiInput
                        v-if="resource.capabilities.searchable"
                        type="search"
                        :model-value="table.search.value"
                        :placeholder="searchPlaceholder"
                        @update:model-value="table.setSearch"
                    />
                    <slot name="afterSearch" v-bind="scope" />
                </div>

                <div class="tb-action-group">
                    <slot name="beforeActions" v-bind="scope" />
                    <span
                        v-if="actions.selectedItems.value.length"
                        class="tb-selected-count"
                    >
                        {{ actions.selectedItems.value.length }} selected
                    </span>
                    <UiButton
                        v-for="action in actions.bulkActions.value"
                        :key="action.key"
                        :variant="
                            action.variant === 'destructive'
                                ? 'outline'
                                : 'default'
                        "
                        :disabled="
                            actions.selectedItems.value.length === 0 ||
                            actions.isPerformingAction.value
                        "
                        @click="actions.performAction(action)"
                    >
                        {{ action.label }}
                    </UiButton>
                    <details
                        v-if="
                            resource.columns.some((column) => column.toggleable)
                        "
                        class="tb-column-toggle"
                    >
                        <summary>Columns</summary>
                        <div class="tb-column-toggle-panel">
                            <label
                                v-for="column in resource.columns.filter(
                                    (candidate) => candidate.toggleable,
                                )"
                                :key="column.attribute"
                            >
                                <UiCheckbox
                                    :model-value="
                                        resource.state.columns[
                                            column.attribute
                                        ] !== false
                                    "
                                    @update:model-value="
                                        table.toggleColumn(column.attribute)
                                    "
                                />
                                <span>{{ column.header }}</span>
                            </label>
                        </div>
                    </details>
                    <slot name="afterActions" v-bind="scope" />
                </div>
            </div>
        </slot>

        <slot name="filters" v-bind="scope">
            <div v-if="resource.filters.length" class="tb-filters">
                <label
                    v-for="filter in resource.filters"
                    :key="filter.attribute"
                    class="tb-filter"
                >
                    <span>{{ filter.label }}</span>
                    <slot
                        :name="`filter(${filter.attribute})`"
                        :filter="filter"
                        :state="resource.state.filters[filter.attribute]"
                        :update="
                            (value: unknown, clause?: string) =>
                                table.setFilter(filter.attribute, value, clause)
                        "
                        v-bind="scope"
                    >
                        <UiSelect
                            v-if="
                                filter.type === 'select' ||
                                filter.type === 'boolean'
                            "
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
                            @update:model-value="
                                updateSelectFilter(filter, $event)
                            "
                        />
                        <UiInput
                            v-else
                            :model-value="
                                String(
                                    resource.state.filters[filter.attribute]
                                        ?.value ?? '',
                                )
                            "
                            @change="
                                table.setFilter(
                                    filter.attribute,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </slot>
                </label>

                <UiButton
                    v-if="table.hasActiveState.value"
                    variant="ghost"
                    @click="table.clearAll"
                >
                    Clear
                </UiButton>
            </div>
        </slot>

        <div class="tb-table-container">
            <slot name="table" v-bind="scope">
                <UiTable class="tb-table">
                    <slot name="thead" v-bind="scope">
                        <UiTableHeader>
                            <UiTableRow>
                                <UiTableHead
                                    v-if="canSelect"
                                    class="tb-selection-cell"
                                >
                                    <UiCheckbox
                                        aria-label="Select current page"
                                        :model-value="
                                            actions.allItemsAreSelected.value
                                        "
                                        @update:model-value="actions.toggleAll"
                                    />
                                </UiTableHead>
                                <UiTableHead
                                    v-for="column in table.visibleColumns.value"
                                    :key="column.attribute"
                                    :data-alignment="column.alignment"
                                >
                                    <slot
                                        :name="`header(${column.attribute})`"
                                        :column="column"
                                        v-bind="scope"
                                    >
                                        <UiButton
                                            v-if="column.sortable"
                                            variant="ghost"
                                            size="sm"
                                            class="tb-sort-button"
                                            @click="
                                                table.setSort(column.attribute)
                                            "
                                        >
                                            {{ column.header
                                            }}{{
                                                sortIndicator(column.attribute)
                                            }}
                                        </UiButton>
                                        <span v-else>{{ column.header }}</span>
                                    </slot>
                                </UiTableHead>
                            </UiTableRow>
                        </UiTableHeader>
                    </slot>

                    <slot name="tbody" v-bind="scope">
                        <UiTableBody>
                            <UiTableRow
                                v-if="resource.results.data.length === 0"
                            >
                                <UiTableCell
                                    :colspan="
                                        table.visibleColumns.value.length +
                                        (canSelect ? 1 : 0)
                                    "
                                    class="tb-empty-state"
                                >
                                    <slot name="emptyState" v-bind="scope"
                                        >No results found.</slot
                                    >
                                </UiTableCell>
                            </UiTableRow>
                            <UiTableRow
                                v-for="(item, index) in resource.results.data"
                                v-else
                                :key="String(item.id ?? index)"
                                :data-selected="
                                    actions.isItemSelected(item, index) ||
                                    undefined
                                "
                            >
                                <UiTableCell
                                    v-if="canSelect"
                                    class="tb-selection-cell"
                                >
                                    <UiCheckbox
                                        aria-label="Select row"
                                        :model-value="
                                            actions.isItemSelected(item, index)
                                        "
                                        @update:model-value="
                                            actions.toggleItem(item, index)
                                        "
                                    />
                                </UiTableCell>
                                <UiTableCell
                                    v-for="column in table.visibleColumns.value"
                                    :key="column.attribute"
                                    :data-alignment="column.alignment"
                                >
                                    <slot
                                        :name="`cell(${column.attribute})`"
                                        :item="item"
                                        :value="
                                            cellValue(item, column.attribute)
                                        "
                                        :column="column"
                                        v-bind="scope"
                                    >
                                        <div
                                            v-if="column.type === 'action'"
                                            class="tb-row-actions"
                                        >
                                            <UiButton
                                                v-for="action in actions.rowActionsFor(
                                                    item,
                                                )"
                                                :key="action.key"
                                                :variant="
                                                    action.variant ===
                                                    'destructive'
                                                        ? 'outline'
                                                        : 'ghost'
                                                "
                                                size="sm"
                                                @click="
                                                    actions.performAction(
                                                        action,
                                                        item,
                                                    )
                                                "
                                            >
                                                {{ action.label }}
                                            </UiButton>
                                        </div>
                                        <Link
                                            v-else-if="
                                                cellUrl(item, column.attribute)
                                            "
                                            :href="
                                                cellUrl(
                                                    item,
                                                    column.attribute,
                                                ) ?? '#'
                                            "
                                            class="tb-cell-link"
                                        >
                                            {{
                                                cellValue(
                                                    item,
                                                    column.attribute,
                                                )
                                            }}
                                        </Link>
                                        <template
                                            v-else-if="
                                                column.type === 'boolean'
                                            "
                                        >
                                            {{
                                                cellValue(
                                                    item,
                                                    column.attribute,
                                                )
                                                    ? "Yes"
                                                    : "No"
                                            }}
                                        </template>
                                        <template v-else>{{
                                            cellValue(item, column.attribute)
                                        }}</template>
                                    </slot>
                                </UiTableCell>
                            </UiTableRow>
                        </UiTableBody>
                    </slot>
                </UiTable>
            </slot>

            <slot v-if="table.isNavigating.value" name="loading" v-bind="scope">
                <div class="tb-loading" role="status">Loading…</div>
            </slot>
        </div>

        <slot v-if="resource.results.total > 0" name="footer" v-bind="scope">
            <div class="tb-footer">
                <span
                    >{{ resource.results.from }}–{{ resource.results.to }} of
                    {{ resource.results.total }}</span
                >
                <div class="tb-pagination">
                    <UiButton
                        variant="outline"
                        :disabled="resource.results.currentPage <= 1"
                        @click="table.setPage(resource.results.currentPage - 1)"
                        >Previous</UiButton
                    >
                    <span
                        >{{ resource.results.currentPage }} /
                        {{ resource.results.lastPage }}</span
                    >
                    <UiButton
                        variant="outline"
                        :disabled="
                            resource.results.currentPage >=
                            resource.results.lastPage
                        "
                        @click="table.setPage(resource.results.currentPage + 1)"
                        >Next</UiButton
                    >
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
        </slot>

        <slot
            v-if="actions.pendingAction.value"
            name="confirmation"
            :pending="actions.pendingAction.value"
            v-bind="scope"
        >
            <div class="tb-confirmation-backdrop">
                <div
                    role="alertdialog"
                    aria-modal="true"
                    class="tb-confirmation"
                >
                    <h2>
                        {{
                            actions.pendingAction.value.action.confirmation
                                ?.title
                        }}
                    </h2>
                    <p>
                        {{
                            actions.pendingAction.value.action.confirmation
                                ?.message
                        }}
                    </p>
                    <div class="tb-confirmation-actions">
                        <UiButton
                            variant="outline"
                            @click="actions.cancelAction"
                        >
                            {{
                                actions.pendingAction.value.action.confirmation
                                    ?.cancelLabel
                            }}
                        </UiButton>
                        <UiButton @click="actions.confirmAction">
                            {{
                                actions.pendingAction.value.action.confirmation
                                    ?.confirmLabel
                            }}
                        </UiButton>
                    </div>
                </div>
            </div>
        </slot>
    </div>
</template>

<style>
.tb-wrapper {
    display: grid;
    gap: 1rem;
}
.tb-topbar,
.tb-search-group,
.tb-action-group,
.tb-filters,
.tb-footer,
.tb-pagination,
.tb-row-actions,
.tb-confirmation-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}
.tb-topbar,
.tb-footer {
    justify-content: space-between;
}
.tb-filter {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}
.tb-table-container {
    position: relative;
    overflow-x: auto;
    border: 1px solid var(--border, #e4e4e7);
    border-radius: var(--radius, 0.625rem);
}
.tb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.tb-table th,
.tb-table td {
    height: 3rem;
    padding: 0.625rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border, #e4e4e7);
}
.tb-table th {
    color: var(--muted-foreground, #71717a);
    font-weight: 500;
}
.tb-table [data-alignment="center"] {
    text-align: center;
}
.tb-table [data-alignment="right"] {
    text-align: right;
}
.tb-table tbody tr:hover,
.tb-table tr[data-selected] {
    background: var(--muted, #f4f4f5);
}
.tb-selection-cell {
    width: 1%;
    white-space: nowrap;
}
.tb-sort-button {
    margin-inline: -0.75rem;
}
.tb-empty-state {
    padding-block: 2rem !important;
    text-align: center !important;
}
.tb-loading {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    background: color-mix(in srgb, var(--background, white) 75%, transparent);
}
.tb-confirmation-backdrop {
    position: fixed;
    z-index: 50;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgb(0 0 0 / 50%);
}
.tb-confirmation {
    width: min(32rem, 100%);
    padding: 1.5rem;
    background: var(--background, white);
    border: 1px solid var(--border, #e4e4e7);
    border-radius: var(--radius, 0.625rem);
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 10%);
}
.tb-confirmation-actions {
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.tb-column-toggle {
    position: relative;
}

.tb-column-toggle > summary {
    height: 2.25rem;
    padding: 0.45rem 0.75rem;
    list-style: none;
    cursor: pointer;
    border: 1px solid var(--border, #e4e4e7);
    border-radius: calc(var(--radius, 0.625rem) - 2px);
}

.tb-column-toggle-panel {
    position: absolute;
    z-index: 20;
    top: calc(100% + 0.375rem);
    right: 0;
    display: grid;
    min-width: 12rem;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--popover, white);
    border: 1px solid var(--border, #e4e4e7);
    border-radius: calc(var(--radius, 0.625rem) - 2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 10%);
}

.tb-column-toggle-panel label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tb-selected-count {
    color: var(--muted-foreground, #71717a);
    font-size: 0.875rem;
}

.tb-cell-link {
    color: inherit;
    font-weight: 500;
    text-decoration: none;
}

.tb-cell-link:hover {
    text-decoration: underline;
}
</style>
