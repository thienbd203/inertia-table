<script setup lang="ts">
import { X } from "@lucide/vue";
import { computed, ref, watch } from "vue";
import { UiButton } from "@/components/ui/button";
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
</script>

<template>
    <div class="tb-active-filter">
        <UiPopover>
            <UiPopoverTrigger as-child>
                <div
                    class="tb-filter-chip"
                    role="button"
                    tabindex="0"
                    :aria-label="`Edit ${filter.label} filter`"
                >
                    <span>{{ filter.label }}</span>
                    <span
                        v-if="filter.showClause !== false"
                        class="tb-filter-symbol"
                    >
                        {{ clauseSymbol(clause) }}
                    </span>
                    <em v-if="display">{{ display }}</em>
                    <button
                        type="button"
                        class="tb-remove-filter"
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
                    <div class="tb-filter-editor">
                        <UiSelect
                            v-if="
                                filter.showClause !== false &&
                                clauseOptions.length > 1
                            "
                            :model-value="clause"
                            :options="clauseOptions"
                            @update:model-value="updateClause"
                        />
                        <UiSelect
                            v-if="
                                filter.type === 'select' ||
                                filter.type === 'set'
                            "
                            :model-value="String(value)"
                            :options="valueOptions"
                            @update:model-value="apply"
                        />
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
                        <UiInput
                            v-else
                            :model-value="String(value)"
                            @change="
                                apply(($event.target as HTMLInputElement).value)
                            "
                        />
                    </div>
                </SlotOutlet>
            </UiPopoverContent>
        </UiPopover>
    </div>
</template>
