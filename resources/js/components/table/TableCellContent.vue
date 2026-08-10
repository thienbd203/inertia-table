<script setup lang="ts">
import { computed } from "vue";
import { useTableContext } from "@/context/tableContext";
import { cellMeta, cellValue, displayValue } from "@/helpers/cells";
import { resolveIcon } from "@/icons";
import type { TableColumn, TableItem } from "@/types";
import SlotOutlet from "./SlotOutlet";

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
const image = computed(() => {
    const value = meta.value.image;

    return value && typeof value === "object"
        ? (value as {
              urls?: string[];
              overflow?: number;
              icon?: string | null;
              size?: string;
              position?: "start" | "end";
              rounded?: boolean;
              width?: number | null;
              height?: number | null;
              class?: string | null;
              alt?: string | null;
              title?: string | null;
          })
        : null;
});
const imageIcon = computed(() =>
    image.value?.icon
        ? resolveIcon(
              image.value.icon,
              { column: props.column, item: props.item, value: value.value },
              iconResolver,
          )
        : null,
);
const hasImage = computed(
    () => (image.value?.urls?.length ?? 0) > 0 || imageIcon.value,
);
</script>

<template>
    <SlotOutlet
        v-if="image"
        :name="`image(${column.attribute})`"
        :slot-props="{ item, column, value, image }"
    >
        <span
            v-if="hasImage"
            class="tb-cell-with-image"
            :class="`tb-image-${image.position ?? 'start'}`"
        >
            <span class="tb-image-stack">
                <img
                    v-for="url in image.urls"
                    :key="url"
                    :src="url"
                    :alt="image.alt ?? column.header"
                    :title="image.title ?? undefined"
                    class="tb-cell-image"
                    :class="[
                        `tb-image-${image.size ?? 'medium'}`,
                        image.rounded && 'tb-image-rounded',
                        image.class,
                    ]"
                    :style="{
                        width: image.width ? `${image.width}px` : undefined,
                        height: image.height ? `${image.height}px` : undefined,
                    }"
                />
                <component
                    :is="imageIcon"
                    v-if="imageIcon"
                    class="tb-cell-image tb-image-icon"
                />
                <span v-if="image.overflow" class="tb-image-overflow">
                    +{{ image.overflow }}
                </span>
            </span>
            <template v-if="column.type !== 'image'">
                {{ displayValue(item, column) }}
            </template>
        </span>
        <SlotOutlet
            v-else
            :name="`image-fallback(${column.attribute})`"
            :slot-props="{ item, column, value, image }"
        />
    </SlotOutlet>
    <template v-else-if="column.type === 'image'">
        <SlotOutlet
            :name="`image-fallback(${column.attribute})`"
            :slot-props="{ item, column, value, image: null }"
        />
    </template>
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
