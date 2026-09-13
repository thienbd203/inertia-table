<script setup lang="ts">
import { DataTable, type TableResource } from "@musing/inertia-table-vue";

type Topic = { id: number; name: string; status: string };
defineProps<{
    publishedTopics: TableResource<Topic>;
    draftTopics: TableResource<Topic>;
}>();
</script>

<template>
    <main>
        <h1>Two independent tables</h1>
        <section aria-labelledby="published-heading">
            <h2 id="published-heading">Published topics</h2>
            <DataTable :resource="publishedTopics" />
        </section>
        <section aria-labelledby="draft-heading">
            <h2 id="draft-heading">Draft topics</h2>
            <DataTable :resource="draftTopics" />
        </section>
    </main>
</template>
