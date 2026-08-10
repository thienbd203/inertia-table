import { computed, ref, watch } from "vue";
import { useTableContext } from "@/context/tableContext";
import type { TableFilter } from "@/types";

const valuelessClauses = ["is_true", "is_false", "is_set", "is_not_set"];

export function useFilterEditor(filter: TableFilter) {
    const { resource, table } = useTableContext();
    const state = computed(
        () => resource.value.state.filters[filter.attribute],
    );
    const clause = ref(state.value?.clause ?? filter.clauses[0] ?? "equals");
    const value = ref<unknown>(state.value?.value ?? "");

    watch(state, (next) => {
        clause.value = next?.clause ?? filter.clauses[0] ?? "equals";
        value.value = next?.value ?? "";
    });

    const clauseOptions = computed(() =>
        filter.clauses.map((candidate) => ({
            value: candidate,
            label: candidate
                .replaceAll("_", " ")
                .replace(/\b\w/g, (letter) => letter.toUpperCase()),
        })),
    );
    const valueOptions = computed(() =>
        filter.type === "boolean"
            ? [
                  { label: "Yes", value: "1" },
                  { label: "No", value: "0" },
              ]
            : filter.options.map((option) => ({
                  label: option.label,
                  value: String(option.value),
              })),
    );
    const isRangeClause = computed(() =>
        ["between", "not_between"].includes(clause.value),
    );
    const isValuelessClause = computed(() =>
        valuelessClauses.includes(clause.value),
    );

    function update(nextValue: unknown = value.value) {
        value.value = nextValue;
        table.setFilter(filter.attribute, nextValue, clause.value);
    }

    function updateClause(nextClause: string) {
        clause.value = nextClause;

        if (valuelessClauses.includes(nextClause)) {
            table.setFilter(filter.attribute, true, nextClause);
        } else if (value.value !== "") {
            update();
        }
    }

    function rangeValue(index: 0 | 1): string {
        return Array.isArray(value.value)
            ? String(value.value[index] ?? "")
            : "";
    }

    function updateRangeValue(index: 0 | 1, nextValue: string | number) {
        const range = [rangeValue(0), rangeValue(1)];
        range[index] = String(nextValue);
        value.value = range;

        if (range[0] !== "" && range[1] !== "") update(range);
    }

    return {
        clause,
        clauseOptions,
        isRangeClause,
        isValuelessClause,
        rangeValue,
        state,
        table,
        update,
        updateClause,
        updateRangeValue,
        value,
        valueOptions,
    };
}
