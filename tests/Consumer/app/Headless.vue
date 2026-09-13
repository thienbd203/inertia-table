<script setup lang="ts">
import { useTable, type TableResource } from "@musing/inertia-table-vue";

type Topic = { id: number; name: string; status: string };
const props = defineProps<{ topics: TableResource<Topic> }>();
const { search, setSearch, setSort, isNavigating } = useTable(
    () => props.topics,
);

function searchInput(event: Event) {
    setSearch((event.target as HTMLInputElement).value);
}
</script>

<template>
    <main>
        <h1>Headless topics</h1>
        <label v-if="topics.capabilities.searchable">
            Search topics
            <input type="search" :value="search" @input="searchInput" />
        </label>
        <table :aria-busy="isNavigating">
            <caption>
                Topics returned by the server
            </caption>
            <thead>
                <tr>
                    <th scope="col">
                        <button
                            v-if="
                                topics.columns.some(
                                    (column) =>
                                        column.attribute === 'name' &&
                                        column.sortable,
                                )
                            "
                            type="button"
                            :disabled="isNavigating"
                            @click="setSort('name')"
                        >
                            Sort by name
                        </button>
                        <span v-else>Name</span>
                    </th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="topic in topics.results.data" :key="topic.id">
                    <td>{{ topic.name }}</td>
                    <td>{{ topic.status }}</td>
                </tr>
                <tr v-if="topics.results.data.length === 0">
                    <td colspan="2">No matching topics.</td>
                </tr>
            </tbody>
        </table>
    </main>
</template>
