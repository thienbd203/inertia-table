import { h, ref } from "vue";
import { expect, it } from "vitest";
import FilterValueControl from "../resources/js/components/table/filters/FilterValueControl.vue";
import { mountWithTableContext } from "./harness";
import { topicResource } from "./fixtures";

it("focuses the set filter button when its editor opens", () => {
    const control = ref<InstanceType<typeof FilterValueControl> | null>(null);
    const filter = {
        ...topicResource().filters[0],
        type: "set" as const,
        clauses: ["in"],
    };
    const { wrapper } = mountWithTableContext({
        render: () =>
            h(FilterValueControl, {
                ref: control,
                filter,
                clause: "in",
                modelValue: [],
                debounceTime: 0,
            }),
    });
    expect(control.value).not.toBeNull();
    control.value!.focus();
    expect(document.activeElement).toBe(wrapper.get("button").element);
});
