import { h, ref, nextTick } from "vue";
import { it, expect, vi } from "vitest";
import FilterValueControl from "../resources/js/components/table/filters/FilterValueControl.vue";
import { mountWithTableContext } from "./harness";
import { RangeCalendarRoot } from "reka-ui";
import { parseDate } from "@internationalized/date";

function mountControl(
    initialClause = "equals",
    type: "numeric" | "date" = "numeric",
) {
    vi.useFakeTimers();
    const clause = ref(initialClause);
    const update = vi.fn();
    const { wrapper } = mountWithTableContext({
        render: () =>
            h(FilterValueControl, {
                filter: {
                    attribute: "score",
                    label: "Score",
                    type,
                    clauses: ["equals", "between"],
                    options: [],
                    meta: {},
                },
                clause: clause.value,
                modelValue: "",
                debounceTime: 300,
                "onUpdate:modelValue": update,
            }),
    });
    return { wrapper, clause, update };
}

it("drops a pending scalar value when the clause changes to a range", async () => {
    const { wrapper, clause, update } = mountControl();
    await wrapper.get("input").setValue("15");
    clause.value = "between";
    await nextTick();
    vi.advanceTimersByTime(300);
    expect(update).not.toHaveBeenCalled();
});

it("drops a pending complete range when one endpoint is cleared", async () => {
    const { wrapper, update } = mountControl("between");
    const inputs = wrapper.findAll("input");
    await inputs[0].setValue("15");
    await inputs[1].setValue("35");
    await inputs[1].setValue("");
    vi.advanceTimersByTime(300);
    expect(update).not.toHaveBeenCalled();
    await inputs[1].setValue("40");
    vi.advanceTimersByTime(300);
    expect(update).toHaveBeenCalledExactlyOnceWith(["15", "40"]);
});

it("drops pending input when the editor unmounts", async () => {
    const { wrapper, update } = mountControl();
    await wrapper.get("input").setValue("15");
    wrapper.unmount();
    vi.advanceTimersByTime(300);
    expect(update).not.toHaveBeenCalled();
});

it("only emits a date range after both calendar endpoints are selected", async () => {
    const { wrapper, update } = mountControl("between", "date");
    const calendar = wrapper.findComponent(RangeCalendarRoot);
    const start = parseDate("2026-01-01");
    calendar.vm.$emit("update:modelValue", { start });
    await nextTick();
    vi.advanceTimersByTime(300);
    expect(update).not.toHaveBeenCalled();
    calendar.vm.$emit("update:modelValue", {
        start,
        end: parseDate("2026-01-04"),
    });
    await nextTick();
    vi.advanceTimersByTime(300);
    expect(update).toHaveBeenCalledExactlyOnceWith([
        "2026-01-01",
        "2026-01-04",
    ]);
});
