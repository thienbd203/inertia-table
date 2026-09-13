<script setup lang="ts">
import { ref } from "vue";
import {
    DataTable,
    type TableResource,
    type TableAction,
    type TableKey,
} from "@musing/inertia-table-vue";

type Topic = { id: number; name: string };
const props = defineProps<{ topics: TableResource<Topic> }>();
const preview = ref("");

function previewAction(
    action: TableAction,
    keys: TableKey[],
    onFinish: () => void,
) {
    try {
        if (action.key !== "preview") return;
        const topic = props.topics.results.data.find(
            (row) => row.id === keys[0],
        );
        preview.value = topic
            ? `Preview: ${topic.name}`
            : "Topic is no longer on this page.";
    } finally {
        onFinish();
    }
}
</script>

<template>
    <main>
        <h1>Custom cells and actions</h1>
        <DataTable :resource="topics" @custom-action="previewAction">
            <template #cell(name)="{ item }">
                <strong>{{ item.name }}</strong>
            </template>
            <template #action(preview)="{ item, execute }">
                <button v-if="item" type="button" @click="execute()">
                    Preview {{ item.name }}
                </button>
            </template>
        </DataTable>
        <p role="status">{{ preview }}</p>
    </main>
</template>
