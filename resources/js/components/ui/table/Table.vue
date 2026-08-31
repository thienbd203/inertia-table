<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref } from "vue";
import { cn } from "@/lib/utils";
const props = defineProps<{
    class?: HTMLAttributes["class"];
    containerClass?: HTMLAttributes["class"];
}>();

const container = ref<HTMLElement | null>(null);
const table = ref<HTMLTableElement | null>(null);
const scrolledFromStart = ref(false);
const scrolledFromEnd = ref(false);
let resizeObserver: ResizeObserver | null = null;
let updateFrame: number | null = null;

function updateScrollState() {
    const element = container.value;
    if (!element) return;

    const maxScroll = Math.max(element.scrollWidth - element.clientWidth, 0);
    const direction = window.getComputedStyle(element).direction;
    const rawOffset = element.scrollLeft;
    const offsetFromStart =
        direction === "rtl"
            ? rawOffset <= 0
                ? -rawOffset
                : maxScroll - rawOffset
            : rawOffset;
    const normalizedOffset = Math.min(Math.max(offsetFromStart, 0), maxScroll);

    scrolledFromStart.value = normalizedOffset > 1;
    scrolledFromEnd.value = maxScroll - normalizedOffset > 1;
}

onMounted(() => {
    resizeObserver =
        typeof ResizeObserver === "undefined"
            ? null
            : new ResizeObserver(updateScrollState);
    if (container.value) resizeObserver?.observe(container.value);
    if (table.value) resizeObserver?.observe(table.value);
    window.addEventListener("resize", updateScrollState);
    void nextTick(() => {
        updateFrame = window.requestAnimationFrame(updateScrollState);
    });
});

onUpdated(updateScrollState);
onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    if (updateFrame !== null) window.cancelAnimationFrame(updateFrame);
    window.removeEventListener("resize", updateScrollState);
});
</script>
<template>
    <div
        ref="container"
        data-slot="table-container"
        :data-scrolled-from-start="scrolledFromStart ? '' : undefined"
        :data-scrolled-from-end="scrolledFromEnd ? '' : undefined"
        :class="
            cn(
                'relative w-full overflow-auto border rounded-md mt-4',
                props.containerClass,
            )
        "
        @scroll.passive="updateScrollState"
    >
        <table
            ref="table"
            data-slot="table"
            :class="cn('w-full caption-bottom text-sm', props.class)"
        >
            <slot />
        </table>
    </div>
</template>
