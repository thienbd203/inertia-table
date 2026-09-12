<script setup lang="ts">
import {
    DataTable,
    type TableResource,
    type TableAction,
    type TableColumn,
    type TableExport,
    type TableKey,
    type TableSelection,
    type QueuedActionStatus,
    type QueuedExportStatus,
} from "@musing/inertia-table-vue";

type Topic = { id: number; name: string };
defineProps<{ topics: TableResource<Topic> }>();
function row(item: Topic, column: TableColumn | null) {
    item.name.toUpperCase();
    column?.header.toUpperCase();
}
function custom(
    action: TableAction,
    keys: TableKey[],
    finish: () => void,
    selection: TableSelection,
) {
    finish();
}
function success(
    action: TableAction,
    keys: TableKey[],
    selection: TableSelection,
) {}
function error(
    action: TableAction,
    keys: TableKey[],
    error: unknown,
    selection: TableSelection,
) {}
function queued(
    action: TableAction,
    status: QueuedActionStatus,
    selection: TableSelection,
) {}
function exported(definition: TableExport) {}
function exportQueued(definition: TableExport, status: QueuedExportStatus) {}
function exportError(definition: TableExport, error: Error) {}
function wrongRow(item: { missing: string }) {}
function wrongError(
    action: TableAction,
    keys: TableKey[],
    error: string,
    selection: TableSelection,
) {}
function wrongQueued(
    action: TableAction,
    status: number,
    selection: TableSelection,
) {}
</script>

<template>
    <DataTable
        :resource="topics"
        :row-key="(item) => item.id"
        @row-click="row"
        @custom-action="custom"
        @action-success="success"
        @action-error="error"
        @action-queued="queued"
        @action-progress="queued"
        @export-success="exported"
        @export-queued="exportQueued"
        @export-error="exportError"
    />
    <!-- @vue-expect-error row handlers must accept the resource row type -->
    <DataTable :resource="topics" @row-click="wrongRow" />
    <!-- @vue-expect-error action errors are unknown, not necessarily strings -->
    <DataTable :resource="topics" @action-error="wrongError" />
    <!-- @vue-expect-error queued status is a structured payload -->
    <DataTable :resource="topics" @action-queued="wrongQueued" />
    <!-- @vue-expect-error row keys must be string or number -->
    <DataTable :resource="topics" :row-key="() => false" />
</template>
