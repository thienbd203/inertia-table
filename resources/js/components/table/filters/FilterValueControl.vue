<script setup lang="ts">
import { useDebounceFn } from "@vueuse/core";
import { computed, onScopeDispose, ref, watch } from "vue";
import { LoaderCircle, Search } from "@lucide/vue";
import { UiInput } from "@/components/ui/input";
import { UiButton } from "@/components/ui/button";
import {
    UiDropdownMenu,
    UiDropdownMenuCheckboxItem,
    UiDropdownMenuContent,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    NativeSelect,
    NativeSelectOption,
} from "@/components/ui/native-select";
import type { TableFilter } from "@/types";
import { useTableContext } from "@/context/tableContext";
import { filterClauseValueKind } from "@/filters";
import FilterDateCalendar from "./FilterDateCalendar.vue";
import FilterDateRangeCalendar from "./FilterDateRangeCalendar.vue";

const props = defineProps<{
    filter: TableFilter;
    clause: string;
    modelValue: unknown;
    debounceTime: number;
}>();
const emit = defineEmits<{ "update:modelValue": [value: unknown] }>();
const { i18n, table } = useTableContext();
const emitInputValue = useDebounceFn(
    (value: unknown) => emit("update:modelValue", value),
    props.debounceTime,
);
watch(
    () => props.clause,
    () => emitInputValue.cancel(),
    { flush: "sync" },
);
onScopeDispose(() => emitInputValue.cancel());
const valueKind = computed(() =>
    filterClauseValueKind(props.filter, props.clause),
);
const isRange = computed(() => valueKind.value === "range");
const isValueless = computed(() => valueKind.value === "none");
const control = computed<"none" | "select" | "range" | "input">(() => {
    if (isValueless.value) {
        return "none";
    }

    if (["select", "set"].includes(props.filter.type)) {
        return "select";
    }

    return isRange.value ? "range" : "input";
});
const showsSearchIcon = computed(
    () =>
        (control.value === "input" && props.filter.type === "text") ||
        (control.value === "select" && props.filter.type === "set"),
);
const allowsMultipleValues = computed(
    () => props.filter.multiple || ["in", "not_in"].includes(props.clause),
);
const setValue = computed(() => {
    const values = Array.isArray(props.modelValue)
        ? props.modelValue.map((value) => String(value))
        : [String(props.modelValue ?? "")];

    return allowsMultipleValues.value ? values : (values[0] ?? "");
});
const selectedOptionLabels = computed(() => {
    const selected = new Set(
        (Array.isArray(setValue.value)
            ? setValue.value
            : [setValue.value]
        ).filter((value) => value !== ""),
    );

    return props.filter.options
        .filter((option) => selected.has(String(option.value)))
        .map((option) => option.label);
});
const multipleSelectLabel = computed(() => {
    const labels = selectedOptionLabels.value;

    if (labels.length === 0) return i18n.t("selectOptions");
    if (labels.length <= 2) return labels.join(", ");

    return i18n.t("optionsSelected", { count: labels.length });
});
const inputType = computed<"date" | "number" | "text">(() =>
    props.filter.type === "date"
        ? "date"
        : props.filter.type === "numeric"
          ? "number"
          : "text",
);
const range = computed<[string, string]>(() => {
    const value = Array.isArray(props.modelValue) ? props.modelValue : [];
    return [String(value[0] ?? ""), String(value[1] ?? "")];
});
const draftRange = ref<[string, string]>(range.value);
const draftInput = ref(String(props.modelValue ?? ""));
const isInputFocused = ref(false);
const valueControl = ref<{ focus: () => void } | null>(null);
const setTrigger = ref<InstanceType<typeof UiButton> | null>(null);
const isLoadingOptions = computed(() =>
    table.isFilterOptionsLoading(props.filter.attribute),
);

watch(range, (nextRange) => {
    draftRange.value = nextRange;
});

watch(
    () => props.modelValue,
    (nextValue) => {
        if (!isInputFocused.value) {
            draftInput.value = String(nextValue ?? "");
        }
    },
);

function updateInput(value: string | number) {
    draftInput.value = String(value);

    emitInputValue(
        props.filter.type === "text" ? draftInput.value.trim() : value,
    );
}

function updateSetValue(value: unknown) {
    emit(
        "update:modelValue",
        allowsMultipleValues.value
            ? (Array.isArray(value) ? value : [value]).map((candidate) =>
                  String(candidate),
              )
            : value,
    );
}

function toggleSetOption(value: string) {
    const selected = new Set(
        Array.isArray(setValue.value) ? setValue.value : [setValue.value],
    );

    if (selected.has(value)) {
        selected.delete(value);
    } else {
        selected.add(value);
    }

    emit(
        "update:modelValue",
        [...selected].filter((candidate) => candidate !== ""),
    );
}

function ensureLazyOptions(): void {
    if (props.filter.lazy && !props.filter.lazyLoaded) {
        table.loadFilterOptions(props.filter.attribute);
    }
}

function updateRange(index: 0 | 1, value: string | number) {
    const next = [...draftRange.value] as [string, string];
    next[index] = String(value);
    updateRangeValue(next);
}

function updateRangeValue(value: [string, string]) {
    const next = [String(value[0]), String(value[1])] as [string, string];
    draftRange.value = next;

    if (next[0] !== "" && next[1] !== "") emitInputValue(next);
    else emitInputValue.cancel();
}

defineExpose({
    focus: () => {
        ensureLazyOptions();
        if (control.value === "select" && allowsMultipleValues.value) {
            const button = setTrigger.value?.$el;
            if (button instanceof HTMLElement) button.focus();
            return;
        }
        valueControl.value?.focus();
    },
});
</script>

<template>
    <div v-if="control !== 'none'" class="flex items-center gap-2 mt-2">
        <Search v-if="showsSearchIcon" class="size-5" />

        <UiDropdownMenu
            v-if="control === 'select' && allowsMultipleValues"
            @update:open="(open) => open && ensureLazyOptions()"
        >
            <UiDropdownMenuTrigger as-child>
                <UiButton
                    ref="setTrigger"
                    variant="outline"
                    class="flex-1 justify-between font-normal"
                    :data-filter-value="filter.attribute"
                >
                    <span class="truncate">{{ multipleSelectLabel }}</span>
                    <LoaderCircle
                        v-if="isLoadingOptions"
                        class="size-4 animate-spin"
                    />
                </UiButton>
            </UiDropdownMenuTrigger>
            <UiDropdownMenuContent align="start" class="min-w-56">
                <div
                    v-if="isLoadingOptions && filter.options.length === 0"
                    class="text-muted-foreground flex items-center gap-2 px-3 py-3 text-sm"
                >
                    <LoaderCircle class="size-4 animate-spin" />
                    {{ i18n.t("loading") }}
                </div>
                <template v-else>
                    <UiDropdownMenuCheckboxItem
                        v-for="option in filter.options"
                        :key="String(option.value)"
                        :model-value="setValue.includes(String(option.value))"
                        @select.prevent="toggleSetOption(String(option.value))"
                    >
                        {{ option.label }}
                    </UiDropdownMenuCheckboxItem>
                </template>
            </UiDropdownMenuContent>
        </UiDropdownMenu>

        <NativeSelect
            v-else-if="control === 'select'"
            ref="valueControl"
            class="flex-1"
            :model-value="setValue"
            :data-filter-value="filter.attribute"
            :disabled="isLoadingOptions"
            @focus="ensureLazyOptions"
            @update:model-value="updateSetValue"
        >
            <NativeSelectOption
                v-for="option in filter.options"
                :key="String(option.value)"
                :value="String(option.value)"
            >
                {{ option.label }}
            </NativeSelectOption>
        </NativeSelect>

        <div v-else-if="control === 'range'" class="tb-filter-range flex-1">
            <FilterDateRangeCalendar
                v-if="filter.type === 'date'"
                ref="valueControl"
                :model-value="draftRange"
                :data-filter-value="filter.attribute"
                @update:model-value="updateRangeValue"
            />
            <UiInput
                v-else
                ref="valueControl"
                type="number"
                :model-value="draftRange[0]"
                :data-filter-value="filter.attribute"
                @update:model-value="(value) => updateRange(0, value)"
            />
            <UiInput
                v-if="filter.type !== 'date'"
                :type="filter.type === 'date' ? 'date' : 'number'"
                :model-value="draftRange[1]"
                @update:model-value="(value) => updateRange(1, value)"
            />
        </div>

        <FilterDateCalendar
            v-else-if="filter.type === 'date'"
            ref="valueControl"
            class="flex-1"
            :model-value="draftInput"
            :data-filter-value="filter.attribute"
            @update:model-value="updateInput"
        />

        <UiInput
            v-else
            ref="valueControl"
            class="flex-1"
            :type="inputType"
            :model-value="draftInput"
            :data-filter-value="filter.attribute"
            @focus="isInputFocused = true"
            @blur="isInputFocused = false"
            @update:model-value="updateInput"
        />
    </div>
</template>
