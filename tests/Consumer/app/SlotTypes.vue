<script setup lang="ts">
import { DataTable, type TableResource } from "@musing/inertia-table-vue";

type Topic = { id: number; name: string };
defineProps<{ topics: TableResource<Topic> }>();
function text(value: string): string {
    return value;
}
</script>

<template>
    <DataTable :resource="topics">
        <template #topbar="{ table }">
            {{ text(table.search.value) }}
            <!-- @vue-expect-error search is a string, not a number -->
            {{ table.search.value.toFixed() }}
        </template>
        <template #summary(name)="{ formatted, value }">
            {{ text(formatted) }}
            <!-- @vue-expect-error summary values need narrowing -->
            {{ text(value) }}
        </template>
        <template #image(name)="{ image, item }">
            {{ text(item.name) }} {{ image.urls }}
            <!-- @vue-expect-error image has no arbitrary fields -->
            {{ image.missingField }}
        </template>
        <template #image-fallback(name)="{ image }">
            {{ image?.urls }}
            <!-- @vue-expect-error fallback image may be null -->
            {{ image.urls }}
        </template>
        <template #cell(name)="{ item, value, table }">
            {{ text(item.name) }}
            {{ table.resource }}
            <!-- @vue-expect-error row fields must retain the consumer model type -->
            {{ item.missingField }}
            <!-- @vue-expect-error raw cell values require narrowing -->
            {{ text(value) }}
        </template>
        <template #header(name)="{ column }">
            {{ text(column.attribute) }}
            <!-- @vue-expect-error header payload has no row -->
            {{ column.missingField }}
        </template>
        <template #action(edit)="{ item, selectedItems, execute }">
            {{ item ? text(item.name) : "Bulk" }}
            {{ selectedItems.map((row) => text(row.name)) }}
            <button @click="execute()">Execute</button>
            <!-- @vue-expect-error bulk actions do not have a row -->
            {{ item.name }}
            <!-- @vue-expect-error execute does not accept a row -->
            <button @click="execute(42)">Invalid</button>
        </template>
        <template #filter(name)="{ state, value, update, setDisplayValue }">
            {{ state?.clause }}
            <button @click="update('Beta')">Update</button>
            <!-- @vue-expect-error filter value must be narrowed -->
            {{ text(value) }}
            <!-- @vue-expect-error display labels are strings or null -->
            <button @click="setDisplayValue(42)">Invalid label</button>
        </template>
    </DataTable>
</template>
