<script setup lang="ts">
import { computed, nextTick, onScopeDispose, ref } from "vue";
import { useTableContext } from "@/context/tableContext";
import type { TableColumn } from "@/types";

const KEYBOARD_STEP = 10;
const props = defineProps<{ column: TableColumn }>();
const { table, sticky, i18n } = useTableContext();
const handle = ref<HTMLElement | null>(null);
const currentWidth = computed(() => table.columnWidth(props.column.attribute));
let startX = 0;
let startWidth = 0;
let pointerId: number | null = null;
let pendingWidth: number | null = null;
let frame: number | null = null;

function direction(): 1 | -1 {
    const element = handle.value?.closest("[data-slot=table-container]");

    return element && window.getComputedStyle(element).direction === "rtl"
        ? -1
        : 1;
}

function applyPendingWidth() {
    frame = null;
    if (pendingWidth === null) return;

    table.setColumnWidth(props.column.attribute, pendingWidth);
    pendingWidth = null;
}

function scheduleWidth(width: number) {
    pendingWidth = width;
    if (frame === null) frame = window.requestAnimationFrame(applyPendingWidth);
}

function stopResize() {
    if (frame !== null) window.cancelAnimationFrame(frame);
    if (pendingWidth !== null) applyPendingWidth();
    if (pointerId !== null && handle.value?.hasPointerCapture?.(pointerId)) {
        handle.value.releasePointerCapture(pointerId);
    }
    pointerId = null;
    if (document.activeElement !== handle.value) {
        table.setResizingColumn(null);
    }
    window.removeEventListener("pointermove", onPointerMove);
    window.removeEventListener("pointerup", stopResize);
    window.removeEventListener("pointercancel", stopResize);
    void nextTick(sticky.measureAll);
}

function onPointerMove(event: PointerEvent) {
    if (event.pointerId !== pointerId) return;
    scheduleWidth(startWidth + (event.clientX - startX) * direction());
}

function onPointerDown(event: PointerEvent) {
    if (
        !event.isPrimary ||
        (event.pointerType === "mouse" && event.button !== 0)
    ) {
        return;
    }

    const cell = handle.value?.closest<HTMLElement>("[data-slot=table-head]");
    startX = event.clientX;
    startWidth =
        currentWidth.value ??
        cell?.getBoundingClientRect().width ??
        KEYBOARD_STEP;
    pointerId = event.pointerId;
    event.preventDefault();
    table.setResizingColumn(props.column.attribute);
    handle.value?.setPointerCapture?.(event.pointerId);
    window.addEventListener("pointermove", onPointerMove, { passive: true });
    window.addEventListener("pointerup", stopResize, { once: true });
    window.addEventListener("pointercancel", stopResize, { once: true });
}

function onKeydown(event: KeyboardEvent) {
    if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;

    event.preventDefault();
    const physicalDelta = event.key === "ArrowRight" ? 1 : -1;
    const width =
        currentWidth.value ?? props.column.width ?? props.column.minWidth ?? 1;
    table.setColumnWidth(
        props.column.attribute,
        width + physicalDelta * direction() * KEYBOARD_STEP,
    );
}

function onFocus() {
    table.setResizingColumn(props.column.attribute);
}

function onBlur() {
    if (pointerId === null) table.setResizingColumn(null);
}

function onPointerEnter() {
    table.setResizingColumn(props.column.attribute);
}

function onPointerLeave() {
    if (pointerId === null && document.activeElement !== handle.value) {
        table.setResizingColumn(null);
    }
}

onScopeDispose(() => {
    stopResize();
    if (table.resizingColumn.value === props.column.attribute) {
        table.setResizingColumn(null);
    }
});
</script>

<template>
    <span
        ref="handle"
        class="tb-column-resize-handle"
        role="separator"
        aria-orientation="vertical"
        :aria-label="
            i18n.t('resizeColumn', {
                column: column.header,
            })
        "
        :aria-valuemin="column.minWidth ?? 1"
        :aria-valuemax="column.maxWidth ?? undefined"
        :aria-valuenow="currentWidth ?? undefined"
        tabindex="0"
        @blur="onBlur"
        @dblclick="table.resetColumnWidth(column.attribute)"
        @focus="onFocus"
        @keydown="onKeydown"
        @pointerdown="onPointerDown"
        @pointerenter="onPointerEnter"
        @pointerleave="onPointerLeave"
    />
</template>
