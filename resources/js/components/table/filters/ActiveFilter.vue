<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import {
    UiPopover,
    UiPopoverContent,
    UiPopoverTrigger,
} from "@/components/ui/popover";
import {
    UiTooltip,
    UiTooltipContent,
    UiTooltipProvider,
    UiTooltipTrigger,
} from "@/components/ui/tooltip";
import { filterFullDisplayValue } from "@/filters";
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
const filterEditor = ref<InstanceType<typeof FilterEditor> | null>(null);
const state = computed(
    () => resource.value.state.filters[props.filter.attribute],
);
const isCompact = computed(() => {
    const value = state.value?.value;

    return (
        Boolean(props.filter.compactDisplayLabel) &&
        Array.isArray(value) &&
        value.length > 1
    );
});
const compactTooltip = computed(() =>
    filterFullDisplayValue(props.filter, state.value),
);
const valuelessClauses = ["is_true", "is_false", "is_set", "is_not_set"];

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

async function focusValueControl(event: Event) {
    if (valuelessClauses.includes(state.value?.clause ?? "")) {
        return;
    }

    event.preventDefault();
    await nextTick();

    filterEditor.value?.focusValueControl();
}
</script>

<template>
    <div class="tb-active-filter">
        <UiTooltipProvider>
            <UiTooltip>
                <UiTooltipTrigger as-child>
                    <span class="inline-flex">
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
                            <UiPopoverContent
                                align="start"
                                class="DropdownMenuContentAnimate w-fit"
                                @open-auto-focus="focusValueControl"
                            >
                                <FilterEditor
                                    ref="filterEditor"
                                    :filter="filter"
                                    @close="isOpen = false"
                                    @update:display-value="
                                        displayValue = $event
                                    "
                                />
                            </UiPopoverContent>
                        </UiPopover>
                    </span>
                </UiTooltipTrigger>
                <UiTooltipContent
                    v-if="isCompact && compactTooltip"
                    class="pointer-events-none"
                >
                    {{ compactTooltip }}
                </UiTooltipContent>
            </UiTooltip>
        </UiTooltipProvider>
    </div>
</template>
