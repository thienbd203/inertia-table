import { h, ref, nextTick } from "vue";
import { expect, it } from "vitest";
import FilterValueControl from "../resources/js/components/table/filters/FilterValueControl.vue";
import { mountWithTableContext } from "./harness";
import { topicResource } from "./fixtures";
import ActiveFilter from "../resources/js/components/table/filters/ActiveFilter.vue";
import { UiPopover } from "../resources/js/components/ui/popover";
import { inertiaReload } from "./inertiaMock";

it("keeps an editor closed when its pending lazy response finishes", async () => {
    const filter = {
        ...topicResource().filters[0],
        type: "set" as const,
        clauses: ["in"],
        lazy: true,
        lazyLoaded: false,
        options: [],
    };
    const { wrapper, table, resource } = mountWithTableContext(
        {
            render: () => h(ActiveFilter, { filter, autoOpen: true }),
        },
        { filters: [filter] },
    );
    const popover = wrapper.findComponent(UiPopover);
    expect(popover.props("open")).toBe(true);
    table.loadFilterOptions("status");
    await nextTick();
    popover.vm.$emit("update:open", false);
    await nextTick();
    resource.value.filters[0].lazyLoaded = true;
    inertiaReload.mock.calls[0][0].onFinish();
    await nextTick();
    await nextTick();
    expect(popover.props("open")).toBe(false);
});

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
