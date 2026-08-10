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

const emit = defineEmits<{ remove: [] }>();
const displayValue = ref<string | null>(null);
const { resource } = useTableContext();
const props = defineProps<{ filter: TableFilter }>();
const state = computed(
    () => resource.value.state.filters[props.filter.attribute],
);

watch(state, () => {
    displayValue.value = null;
});
</script>

<template>
    <div class="tb-active-filter">
        <UiPopover>
            <UiPopoverTrigger as-child>
                <FilterChip
                    :filter="filter"
                    :state="state"
                    :display-value="displayValue"
                    @remove="emit('remove')"
                />
            </UiPopoverTrigger>
            <UiPopoverContent>
                <FilterEditor
                    :filter="filter"
                    @update:display-value="displayValue = $event"
                />
            </UiPopoverContent>
        </UiPopover>
    </div>
</template>
