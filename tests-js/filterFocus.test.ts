import { h, ref, nextTick } from "vue";
import { expect, it } from "vitest";
import FilterValueControl from "../resources/js/components/table/filters/FilterValueControl.vue";
import { mountWithTableContext } from "./harness";
import { topicResource } from "./fixtures";
import ActiveFilter from "../resources/js/components/table/filters/ActiveFilter.vue";
import { UiPopover } from "../resources/js/components/ui/popover";
import { inertiaReload } from "./inertiaMock";
import FilterEditor from "../resources/js/components/table/filters/FilterEditor.vue";
import DataTable from "../resources/js/DataTable.vue";
import { mount } from "@vue/test-utils";

it("uses separate native filter buttons and restores focus after removal", async () => {
    const resource = topicResource();
    resource.state.filters.status = {
        enabled: true,
        clause: "in",
        value: ["published"],
    };
    const wrapper = mount(DataTable, {
        props: { resource },
        attachTo: document.body,
    });
    const edit = wrapper.get('[aria-label="Edit Status filter"]');
    expect(edit.element.tagName).toBe("BUTTON");
    expect(edit.find("button").exists()).toBe(false);
    await wrapper.get('[aria-label="Remove Status filter"]').trigger("click");
    await nextTick();
    expect(wrapper.find('[aria-label="Edit Status filter"]').exists()).toBe(
        false,
    );
    expect(document.activeElement).toBe(
        wrapper.get("[data-add-filter-trigger]").element,
    );
});

it("names the filter condition and both numeric range endpoints", () => {
    const filter = {
        ...topicResource().filters[0],
        attribute: "price",
        label: "Price",
        type: "numeric" as const,
        clauses: ["equals", "between"],
    };
    const { wrapper } = mountWithTableContext({
        render: () => h(FilterEditor, { filter }),
    });
    expect(wrapper.get("select").attributes("aria-label")).toBe(
        "Price: condition",
    );
    const range = mountWithTableContext({
        render: () =>
            h(FilterValueControl, {
                filter,
                clause: "between",
                modelValue: [10, 20],
                debounceTime: 0,
            }),
    });
    expect(
        range.wrapper
            .findAll("input")
            .map((input) => input.attributes("aria-label")),
    ).toEqual(["Price: minimum", "Price: maximum"]);
});

it("exposes the current sort direction on only the sorted header", async () => {
    const resource = topicResource();
    resource.state.sort = "name";
    const wrapper = mount(DataTable, { props: { resource } });
    expect(wrapper.get("th[aria-sort]").attributes("aria-sort")).toBe(
        "ascending",
    );
    await wrapper.setProps({
        resource: { ...resource, state: { ...resource.state, sort: "-name" } },
    });
    expect(wrapper.findAll("th[aria-sort]")).toHaveLength(1);
    expect(wrapper.get("th[aria-sort]").attributes("aria-sort")).toBe(
        "descending",
    );
});

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
