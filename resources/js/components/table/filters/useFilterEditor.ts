import { computed, ref, watch } from "vue";
import { useTableContext } from "@/context/tableContext";
import type { TableFilter } from "@/types";

const valuelessClauses = ["is_true", "is_false", "is_set", "is_not_set"];
const clauseMessageKeys = {
    after: "clauseAfter",
    before: "clauseBefore",
    between: "clauseBetween",
    contains: "clauseContains",
    ends_with: "clauseEndsWith",
    equal_or_after: "clauseEqualOrAfter",
    equal_or_before: "clauseEqualOrBefore",
    equals: "clauseEquals",
    greater_than: "clauseGreaterThan",
    greater_than_or_equal: "clauseGreaterThanOrEqual",
    in: "clauseIn",
    is_false: "clauseIsFalse",
    is_not_set: "clauseIsNotSet",
    is_set: "clauseIsSet",
    is_true: "clauseIsTrue",
    less_than: "clauseLessThan",
    less_than_or_equal: "clauseLessThanOrEqual",
    not_between: "clauseNotBetween",
    not_contains: "clauseNotContains",
    not_ends_with: "clauseNotEndsWith",
    not_equals: "clauseNotEquals",
    not_in: "clauseNotIn",
    not_starts_with: "clauseNotStartsWith",
    starts_with: "clauseStartsWith",
} as const;

export function useFilterEditor(filter: TableFilter) {
    const { i18n, resource, table } = useTableContext();
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
            label: clauseMessageKeys[
                candidate as keyof typeof clauseMessageKeys
            ]
                ? i18n.t(
                      clauseMessageKeys[
                          candidate as keyof typeof clauseMessageKeys
                      ],
                  )
                : candidate
                      .replaceAll("_", " ")
                      .replace(/\b\w/g, (letter) => letter.toUpperCase()),
        })),
    );
    const valueOptions = computed(() =>
        filter.type === "boolean"
            ? [
                  { label: i18n.t("yes"), value: "1" },
                  { label: i18n.t("no"), value: "0" },
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
        const wasRangeClause = isRangeClause.value;
        const becomesRangeClause = ["between", "not_between"].includes(
            nextClause,
        );
        clause.value = nextClause;

        if (valuelessClauses.includes(nextClause)) {
            table.setFilter(filter.attribute, true, nextClause);
            return;
        }

        if (wasRangeClause !== becomesRangeClause) {
            value.value = becomesRangeClause ? ["", ""] : "";
            return;
        }

        if (
            becomesRangeClause &&
            (!Array.isArray(value.value) ||
                value.value.length < 2 ||
                value.value[0] === "" ||
                value.value[1] === "")
        ) {
            return;
        }

        if (value.value !== "") {
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
