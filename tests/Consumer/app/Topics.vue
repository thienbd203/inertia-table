<script setup lang="ts">
import { DataTable, type TableResource } from "@musing/inertia-table-vue";
import { router } from "@inertiajs/vue3";
import { onMounted, onUnmounted } from "vue";

function reloadShortcut(event: KeyboardEvent) {
    if (event.ctrlKey && event.key === "Enter") {
        event.preventDefault();
        router.reload({ only: ["topics"] });
    }
}
onMounted(() => document.addEventListener("keydown", reloadShortcut, true));
onUnmounted(() =>
    document.removeEventListener("keydown", reloadShortcut, true),
);

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
        <button
            type="button"
            class="mb-4 rounded border px-3 py-2"
            @click="router.cancelAll()"
        >
            Cancel pending requests
        </button>
        <button
            type="button"
            class="mb-4 rounded border px-3 py-2"
            @click="router.reload({ only: ['topics'] })"
        >
            Reload table props (Ctrl+Enter)
        </button>
        <DataTable :resource="topics" />
    </main>
</template>
