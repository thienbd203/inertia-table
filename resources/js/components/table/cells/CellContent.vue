<script setup lang="ts">
import { computed } from "vue";
import { cellMeta } from "@/helpers/cells";
import type { TableColumn, TableItem } from "@/types";
import BadgeCell from "./BadgeCell.vue";
import ImageCell from "./ImageCell.vue";
import { normalizeCellImage } from "./types";
import ValueCell from "./ValueCell.vue";

const props = defineProps<{ item: TableItem; column: TableColumn }>();
const image = computed(() =>
    normalizeCellImage(cellMeta(props.item, props.column.attribute).image),
);
</script>

<template>
    <ImageCell
        v-if="image || column.type === 'image'"
        :item="item"
        :column="column"
        :image="image"
    />
    <BadgeCell
        v-else-if="column.type === 'badge'"
        :item="item"
        :column="column"
    />
    <ValueCell v-else :item="item" :column="column" />
</template>
