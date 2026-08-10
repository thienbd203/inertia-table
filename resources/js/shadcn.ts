import { Check, ChevronDown } from "@lucide/vue";
import {
    CheckboxIndicator,
    CheckboxRoot,
    SelectContent,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from "reka-ui";
import { defineComponent, h, type PropType } from "vue";

function element(name: string, tag: string) {
    const slot = name
        .replace(/^Ui/, "")
        .replace(/([a-z])([A-Z])/g, "$1-$2")
        .toLowerCase();

    return defineComponent({
        name,
        inheritAttrs: false,
        setup(_, { attrs, slots }) {
            return () =>
                h(
                    tag,
                    {
                        ...attrs,
                        "data-slot": slot,
                    },
                    slots.default?.(),
                );
        },
    });
}

export const UiTable = element("UiTable", "table");
export const UiTableHeader = element("UiTableHeader", "thead");
export const UiTableBody = element("UiTableBody", "tbody");
export const UiTableRow = element("UiTableRow", "tr");
export const UiTableHead = element("UiTableHead", "th");
export const UiTableCell = element("UiTableCell", "td");

export const UiButton = defineComponent({
    name: "UiButton",
    inheritAttrs: false,
    props: {
        variant: { type: String, default: "default" },
        size: { type: String, default: "default" },
    },
    setup(props, { attrs, slots }) {
        return () =>
            h(
                "button",
                {
                    ...attrs,
                    "data-slot": "button",
                    "data-variant": props.variant,
                    "data-size": props.size,
                },
                slots.default?.(),
            );
    },
});

export const UiInput = defineComponent({
    name: "UiInput",
    inheritAttrs: false,
    emits: ["update:modelValue"],
    props: { modelValue: { type: [String, Number], default: "" } },
    setup(props, { attrs, emit }) {
        return () =>
            h("input", {
                ...attrs,
                "data-slot": "input",
                value: props.modelValue,
                onInput: (event: Event) =>
                    emit(
                        "update:modelValue",
                        (event.target as HTMLInputElement).value,
                    ),
            });
    },
});

export const UiCheckbox = defineComponent({
    name: "UiCheckbox",
    props: { modelValue: { type: Boolean, default: false } },
    emits: ["update:modelValue"],
    setup(props, { attrs, emit }) {
        return () =>
            h(
                CheckboxRoot,
                {
                    ...attrs,
                    "data-slot": "checkbox",
                    modelValue: props.modelValue,
                    "onUpdate:modelValue": (value: unknown) =>
                        emit("update:modelValue", value === true),
                },
                {
                    default: () =>
                        h(
                            CheckboxIndicator,
                            { "data-slot": "checkbox-indicator" },
                            () => h(Check, { size: 14 }),
                        ),
                },
            );
    },
});

export type UiSelectOption = { label: string; value: string };

export const UiSelect = defineComponent({
    name: "UiSelect",
    props: {
        modelValue: { type: String, default: "" },
        options: {
            type: Array as PropType<UiSelectOption[]>,
            default: () => [],
        },
        placeholder: { type: String, default: "Select…" },
    },
    emits: ["update:modelValue"],
    setup(props, { attrs, emit }) {
        return () =>
            h(
                SelectRoot,
                {
                    modelValue: props.modelValue,
                    "onUpdate:modelValue": (value: unknown) =>
                        emit("update:modelValue", String(value ?? "")),
                },
                {
                    default: () => [
                        h(
                            SelectTrigger,
                            { ...attrs, "data-slot": "select-trigger" },
                            {
                                default: () => [
                                    h(SelectValue, {
                                        placeholder: props.placeholder,
                                    }),
                                    h(SelectIcon, { asChild: true }, () =>
                                        h(ChevronDown, { size: 16 }),
                                    ),
                                ],
                            },
                        ),
                        h(SelectPortal, null, () =>
                            h(
                                SelectContent,
                                {
                                    "data-slot": "select-content",
                                    position: "popper",
                                },
                                () =>
                                    h(SelectViewport, null, () =>
                                        props.options.map((option) =>
                                            h(
                                                SelectItem,
                                                {
                                                    key: option.value,
                                                    value: option.value,
                                                    "data-slot": "select-item",
                                                },
                                                {
                                                    default: () => [
                                                        h(
                                                            SelectItemText,
                                                            null,
                                                            () => option.label,
                                                        ),
                                                        h(
                                                            SelectItemIndicator,
                                                            {
                                                                "data-slot":
                                                                    "select-item-indicator",
                                                            },
                                                            () =>
                                                                h(Check, {
                                                                    size: 14,
                                                                }),
                                                        ),
                                                    ],
                                                },
                                            ),
                                        ),
                                    ),
                            ),
                        ),
                    ],
                },
            );
    },
});
