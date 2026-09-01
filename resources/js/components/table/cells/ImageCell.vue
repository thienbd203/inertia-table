<script setup lang="ts">
import { computed } from "vue";
import { SlotOutlet } from "../shared";
import type { CellImage, CellPresentationProps } from "./types";
import { useCellPresentation } from "./useCellPresentation";

const props = defineProps<
    CellPresentationProps & {
        image: CellImage | null;
    }
>();
const { display, resolveNamedIcon, value } = useCellPresentation(props);
const imageIcon = computed(() => resolveNamedIcon(props.image?.icon));
const hasImage = computed(
    () => (props.image?.urls?.length ?? 0) > 0 || imageIcon.value,
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
                {{ display }}
            </template>
        </span>
        <SlotOutlet
            v-else
            :name="`image-fallback(${column.attribute})`"
            :slot-props="{ item, column, value, image }"
        />
    </SlotOutlet>
    <SlotOutlet
        v-else
        :name="`image-fallback(${column.attribute})`"
        :slot-props="{ item, column, value, image: null }"
    />
</template>
