<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { X } from "@lucide/vue";
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
import { filterClauseValueKind, filterFullDisplayValue } from "@/filters";
import type { TableFilter } from "@/types";
import { useTableContext } from "@/context/tableContext";
import FilterChip from "./FilterChip.vue";
import FilterEditor from "./FilterEditor.vue";

const emit = defineEmits<{
    opened: [];
    remove: [];
}>();
const displayValue = ref<string | null>(null);
const { resource, i18n } = useTableContext();
const props = defineProps<{
    filter: TableFilter;
    autoOpen?: boolean;
}>();
const isOpen = ref(false);
const filterEditor = ref<InstanceType<typeof FilterEditor> | null>(null);
async function remove(event: MouseEvent) {
    const trigger = (event.currentTarget as HTMLElement)
        .closest(".tb-wrapper")
        ?.querySelector<HTMLElement>("[data-add-filter-trigger]");
    emit("remove");
    await nextTick();
    trigger?.focus();
}
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
    if (
        filterClauseValueKind(props.filter, state.value?.clause ?? "") ===
        "none"
    ) {
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
                    <span
                        class="inline-flex items-center rounded-md border border-gray-400 bg-gray-200/75 py-1 text-xs font-medium text-gray-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                        <UiPopover v-model:open="isOpen">
                            <UiPopoverTrigger
                                as-child
                                class="flex items-center rounded-md text-xs"
                            >
                                <FilterChip
                                    :filter="filter"
                                    :state="state"
                                    :display-value="displayValue"
                                />
                            </UiPopoverTrigger>
                            <button
                                type="button"
                                class="tb-remove-filter ms-2 h-full rounded-md py-1 pe-2 text-gray-500 transition-colors hover:text-red-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :aria-label="
                                    i18n.t('removeFilter', {
                                        filter: filter.label,
                                    })
                                "
                                @click="remove"
                            >
                                <X class="size-4" aria-hidden="true" />
                            </button>
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
