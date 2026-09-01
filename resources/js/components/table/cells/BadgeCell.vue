<script setup lang="ts">
import { computed } from "vue";
import type { CellPresentationProps } from "./types";
import { useCellPresentation } from "./useCellPresentation";

const props = defineProps<CellPresentationProps>();
const { display, icon, meta } = useCellPresentation(props);
const variantClass = computed(() => {
    const variant = String(meta.value.variant ?? "default");

    return (
        {
            default:
                "border-transparent bg-secondary text-secondary-foreground",
            danger: "border-transparent bg-destructive text-white",
            destructive: "border-transparent bg-destructive text-white",
            info: "border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
            success:
                "border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300",
            warning:
                "border-transparent bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-300",
        }[variant] ??
        "border-transparent bg-secondary text-secondary-foreground"
    );
});
</script>

<template>
    <span
        class="tb-badge inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="[variantClass, meta.badgeClass]"
        :data-style="meta.variant ?? 'default'"
    >
        <component
            :is="icon"
            v-if="icon"
            class="tb-cell-icon size-4 shrink-0"
        />
        {{ display }}
    </span>
</template>
