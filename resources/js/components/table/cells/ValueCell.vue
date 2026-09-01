<script setup lang="ts">
import type { CellPresentationProps } from "./types";
import { useCellPresentation } from "./useCellPresentation";

const props = defineProps<CellPresentationProps>();
const { display, icon } = useCellPresentation(props);
</script>

<template>
    <span class="tb-cell-content inline-flex items-center gap-1 font-medium">
        <component
            :is="icon"
            v-if="icon"
            class="tb-cell-icon size-4 shrink-0"
        />
        {{ display }}
    </span>
</template>
