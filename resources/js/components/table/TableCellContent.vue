<script setup lang="ts">
import { computed } from "vue";
import { useTableContext } from "@/context/tableContext";
import { cellMeta, cellValue, displayValue } from "@/helpers/cells";
import { resolveIcon } from "@/icons";
import type { TableColumn, TableItem } from "@/types";

const props = defineProps<{ item: TableItem; column: TableColumn }>();
const { iconResolver } = useTableContext();
const value = computed(() => cellValue(props.item, props.column.attribute));
const meta = computed(() => cellMeta(props.item, props.column.attribute));
const iconName = computed(() => {
    if (props.column.type === "boolean") {
        return value.value ? props.column.trueIcon : props.column.falseIcon;
    }

    return typeof meta.value.icon === "string" ? meta.value.icon : null;
});
const icon = computed(() =>
    iconName.value
        ? resolveIcon(
              iconName.value,
              { column: props.column, item: props.item, value: value.value },
              iconResolver,
          )
        : null,
);
</script>

<template>
    <img
        v-if="column.type === 'image' && typeof value === 'string'"
        :src="value"
        :alt="column.header"
        class="tb-cell-image"
    />
    <span
        v-else-if="column.type === 'badge'"
        class="tb-badge"
        :data-style="meta.variant ?? 'default'"
    >
        <component :is="icon" v-if="icon" class="tb-cell-icon" />
        {{ displayValue(item, column) }}
    </span>
    <span v-else class="tb-cell-content">
        <component :is="icon" v-if="icon" class="tb-cell-icon" />
        {{ displayValue(item, column) }}
    </span>
</template>
