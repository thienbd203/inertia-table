<script setup lang="ts">
import { LoaderCircle, Search } from "@lucide/vue";
import { computed, ref, toRef, type ComponentPublicInstance } from "vue";
import { UiButton } from "@/components/ui/button";
import {
    UiDropdownMenu,
    UiDropdownMenuCheckboxItem,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { UiInput } from "@/components/ui/input";
import { useTableContext } from "@/context/tableContext";
import type { TableFilter, TableFilterOption } from "@/types";
import { useRemoteFilterOptions } from "./useRemoteFilterOptions";

const props = defineProps<{
    filter: TableFilter;
    clause: string;
    modelValue: unknown;
}>();
const emit = defineEmits<{ "update:modelValue": [value: unknown] }>();
const { i18n } = useTableContext();
const trigger = ref<ComponentPublicInstance | null>(null);
const allowsMultipleValues = computed(
    () => props.filter.multiple || ["in", "not_in"].includes(props.clause),
);
const selectedValues = computed(() => {
    const values = Array.isArray(props.modelValue)
        ? props.modelValue
        : [props.modelValue];

    return values.map(String).filter(Boolean);
});
const {
    error,
    loadMore,
    loading,
    loadingMore,
    nextCursor,
    options,
    retry,
    search,
} = useRemoteFilterOptions(toRef(props, "filter"), toRef(props, "modelValue"));
const selectedOptionLabels = computed(() => {
    const selected = new Set(selectedValues.value);

    return options.value
        .filter((option) => selected.has(String(option.value)))
        .map((option) => option.label);
});
const triggerLabel = computed(() => {
    const labels = selectedOptionLabels.value;

    if (labels.length === 0) return i18n.t("selectOptions");
    if (labels.length <= 2) return labels.join(", ");

    return i18n.t("optionsSelected", { count: labels.length });
});

function toggle(option: TableFilterOption): void {
    const value = String(option.value);

    if (!allowsMultipleValues.value) {
        emit("update:modelValue", option.value);
        return;
    }

    const selected = new Set(selectedValues.value);
    if (selected.has(value)) selected.delete(value);
    else selected.add(value);
    emit("update:modelValue", [...selected]);
}

defineExpose({
    focus: () => (trigger.value?.$el as HTMLElement | undefined)?.focus(),
});
</script>

<template>
    <UiDropdownMenu>
        <UiDropdownMenuTrigger as-child>
            <UiButton
                ref="trigger"
                variant="outline"
                class="flex-1 justify-between font-normal"
                :data-filter-value="filter.attribute"
            >
                <span class="truncate">{{ triggerLabel }}</span>
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent align="start" class="min-w-64">
            <div v-if="filter.remote?.searchable" class="p-1">
                <div class="relative">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2"
                    />
                    <UiInput
                        v-model="search"
                        class="h-8 ps-8"
                        :placeholder="i18n.t('searchOptions')"
                        @keydown.stop
                    />
                </div>
            </div>

            <div
                v-if="loading"
                class="text-muted-foreground flex items-center gap-2 px-2 py-3 text-sm"
            >
                <LoaderCircle class="size-4 animate-spin" />
                {{ i18n.t("loading") }}
            </div>
            <div v-else-if="error" class="space-y-2 px-2 py-3 text-sm">
                <p class="text-destructive">{{ error }}</p>
                <UiButton variant="outline" size="sm" @click.stop="retry">
                    {{ i18n.t("retry") }}
                </UiButton>
            </div>
            <div
                v-else-if="options.length === 0"
                class="text-muted-foreground px-2 py-3 text-sm"
            >
                {{ i18n.t("noOptions") }}
            </div>
            <template v-else>
                <UiDropdownMenuCheckboxItem
                    v-for="option in options"
                    :key="String(option.value)"
                    :model-value="selectedValues.includes(String(option.value))"
                    :disabled="
                        filter.remote?.withCounts &&
                        option.count === 0 &&
                        !selectedValues.includes(String(option.value))
                    "
                    @select.prevent="toggle(option)"
                >
                    <span class="min-w-0 flex-1 truncate">{{
                        option.label
                    }}</span>
                    <span
                        v-if="filter.remote?.withCounts"
                        class="text-muted-foreground tabular-nums"
                    >
                        {{ option.count ?? 0 }}
                    </span>
                </UiDropdownMenuCheckboxItem>
            </template>

            <UiButton
                v-if="nextCursor && !loading"
                variant="ghost"
                size="sm"
                class="mt-1 w-full"
                :disabled="loadingMore"
                @click.stop="loadMore"
            >
                <LoaderCircle v-if="loadingMore" class="size-4 animate-spin" />
                {{ i18n.t("loadMore") }}
            </UiButton>
        </UiDropdownMenuContent>
    </UiDropdownMenu>
</template>
