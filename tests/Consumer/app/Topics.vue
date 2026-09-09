<script setup lang="ts">
import { DataTable, type TableResource } from "@musing/inertia-table-vue";

type Topic = {
    id: number;
    name: string;
    status: string;
    created_at: string;
};

defineProps<{ topics: TableResource<Topic> }>();
</script>

<template>
    <main class="p-6">
        <h1 class="mb-4 text-xl font-semibold">Consumer topics</h1>
        <DataTable :resource="topics" />
    </main>
</template>
