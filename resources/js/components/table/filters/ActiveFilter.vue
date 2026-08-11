<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
    UiPopover,
    UiPopoverContent,
    UiPopoverTrigger,
} from "@/components/ui/popover";
import type { TableFilter } from "@/types";
import { useTableContext } from "@/context/tableContext";
import FilterChip from "./FilterChip.vue";
import FilterEditor from "./FilterEditor.vue";

const emit = defineEmits<{
    opened: [];
    remove: [];
}>();
const displayValue = ref<string | null>(null);
const { resource } = useTableContext();
const props = defineProps<{
    filter: TableFilter;
    autoOpen?: boolean;
}>();
const isOpen = ref(false);
const state = computed(
    () => resource.value.state.filters[props.filter.attribute],
);

watch(state, () => {
    displayValue.value = null;
});

watch(
    () => props.autoOpen,
    (autoOpen) => {
        if (!autoOpen) {
            return;
        }

        isOpen.value = true;
        emit("opened");
    },
    { immediate: true },
);
</script>

<template>
    <div class="tb-active-filter">
        <UiPopover v-model:open="isOpen">
            <UiPopoverTrigger
                as-child
                class="flex items-center rounded-md border border-gray-400 bg-gray-200/75 text-xs font-medium text-gray-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            >
                <FilterChip
                    :filter="filter"
                    :state="state"
                    :display-value="displayValue"
                    @remove="emit('remove')"
                />
            </UiPopoverTrigger>
            <UiPopoverContent @close-auto-focus="(e) => e.stopPropagation()">
                <FilterEditor
                    :filter="filter"
                    @update:display-value="displayValue = $event"
                />
            </UiPopoverContent>
        </UiPopover>
    </div>
</template>
