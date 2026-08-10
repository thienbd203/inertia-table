<script setup lang="ts">
import { X } from "@lucide/vue";
import { computed, ref, watch } from "vue";
import { UiInput } from "@/components/ui/input";
import {
    UiPopover,
    UiPopoverContent,
    UiPopoverTrigger,
} from "@/components/ui/popover";
import { UiSelect } from "@/components/ui/select";
import { useTableContext } from "@/context/tableContext";
import { clauseSymbol, filterDisplayValue } from "@/filters";
import type { TableFilter } from "@/types";
import SlotOutlet from "./SlotOutlet";
import { Filter } from "@lucide/vue";

const props = defineProps<{ filter: TableFilter }>();
const emit = defineEmits<{ remove: [] }>();
const { resource, table } = useTableContext();

const state = computed(
    () => resource.value.state.filters[props.filter.attribute],
);
const clause = ref(state.value?.clause ?? props.filter.clauses[0] ?? "equals");
const value = ref<unknown>(state.value?.value ?? "");
const displayValue = ref<string | null>(null);

watch(state, (next) => {
    clause.value = next?.clause ?? props.filter.clauses[0] ?? "equals";
    value.value = next?.value ?? "";
    displayValue.value = null;
});

const display = computed(
    () => displayValue.value ?? filterDisplayValue(props.filter, state.value),
);

const clauseOptions = computed(() =>
    props.filter.clauses.map((candidate) => ({
        value: candidate,
        label: candidate
            .replaceAll("_", " ")
            .replace(/\b\w/g, (letter) => letter.toUpperCase()),
    })),
);
const valueOptions = computed(() =>
    props.filter.type === "boolean"
        ? [
              { label: "Yes", value: "1" },
              { label: "No", value: "0" },
          ]
        : props.filter.options.map((option) => ({
              label: option.label,
              value: String(option.value),
          })),
);
const isRangeClause = computed(() =>
    ["between", "not_between"].includes(clause.value),
);

function apply(nextValue: unknown = value.value) {
    value.value = nextValue;
    table.setFilter(props.filter.attribute, nextValue, clause.value);
}

function updateClause(nextClause: string) {
    clause.value = nextClause;
    if (["is_true", "is_false", "is_set", "is_not_set"].includes(nextClause)) {
        table.setFilter(props.filter.attribute, true, nextClause);
    } else if (value.value !== "") apply();
}

function setDisplayValue(nextValue: string | null) {
    displayValue.value = nextValue;
}

function rangeValue(index: 0 | 1): string {
    return Array.isArray(value.value) ? String(value.value[index] ?? "") : "";
}

function updateRangeValue(index: 0 | 1, nextValue: string | number) {
    const nextRange = [rangeValue(0), rangeValue(1)];
    nextRange[index] = String(nextValue);
    value.value = nextRange;

    if (nextRange[0] !== "" && nextRange[1] !== "") {
        apply(nextRange);
    }
}
</script>

<template>
    <div class="tb-active-filter">
        <UiPopover>
            <UiPopoverTrigger
                as-child
                class="flex items-center rounded-md border border-gray-400 bg-gray-200/75 text-xs font-medium text-gray-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            >
                <div
                    class="tb-filter-chip space-x-1 py-1 ps-2 text-sm font-medium"
                    role="button"
                    tabindex="0"
                    :aria-label="`Edit ${filter.label} filter`"
                >
                    <span>{{ filter.label }}</span>
                    <span class="tb-filter-symbol">
                        {{ clauseSymbol(clause) }}
                    </span>
                    <em v-if="display">{{ display }}</em>
                    <button
                        type="button"
                        class="tb-remove-filter ms-2 h-full py-1 pe-2 text-gray-500 transition-colors hover:text-red-500"
                        :aria-label="`Remove ${filter.label} filter`"
                        @click.stop="emit('remove')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </UiPopoverTrigger>
            <UiPopoverContent>
                <SlotOutlet
                    :name="`filter(${filter.attribute})`"
                    :slot-props="{
                        filter,
                        state,
                        update: apply,
                        setDisplayValue,
                        table,
                    }"
                >
                    <div class="tb-filter-editor gap-2">
                        <div
                            v-if="
                                filter.showClause !== false &&
                                clauseOptions.length > 1
                            "
                            class="flex items-center gap-2"
                        >
                            <Filter class="size-5" />
                            <UiSelect
                                :model-value="clause"
                                :options="clauseOptions"
                                @update:model-value="updateClause"
                                class="w-full"
                            />
                        </div>
                        <div
                            v-if="
                                filter.type === 'select' ||
                                filter.type === 'set'
                            "
                            class="flex items-center gap-2"
                        >
                            <Filter class="size-5" />
                            <UiSelect
                                :model-value="String(value)"
                                :options="valueOptions"
                                @update:model-value="apply"
                                class="w-full"
                            />
                        </div>
                        <span
                            v-else-if="
                                [
                                    'is_true',
                                    'is_false',
                                    'is_set',
                                    'is_not_set',
                                ].includes(clause)
                            "
                            class="tb-filter-clause-only"
                        >
                            This filter does not require a value.
                        </span>
                        <div v-else-if="isRangeClause" class="tb-filter-range">
                            <UiInput
                                :type="
                                    filter.type === 'date' ? 'date' : 'number'
                                "
                                :model-value="rangeValue(0)"
                                @update:model-value="
                                    (nextValue) =>
                                        updateRangeValue(0, nextValue)
                                "
                            />
                            <span aria-hidden="true">–</span>
                            <UiInput
                                :type="
                                    filter.type === 'date' ? 'date' : 'number'
                                "
                                :model-value="rangeValue(1)"
                                @update:model-value="
                                    (nextValue) =>
                                        updateRangeValue(1, nextValue)
                                "
                            />
                        </div>
                        <UiInput
                            v-else
                            :type="
                                filter.type === 'date'
                                    ? 'date'
                                    : filter.type === 'numeric'
                                      ? 'number'
                                      : 'text'
                            "
                            :model-value="String(value)"
                            @update:model-value="apply"
                        />
                    </div>
                </SlotOutlet>
            </UiPopoverContent>
        </UiPopover>
    </div>
</template>
