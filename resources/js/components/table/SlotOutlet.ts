import { defineComponent, renderSlot, type PropType } from "vue";
import { useTableContext } from "../../context/tableContext";

export default defineComponent({
    name: "TableSlotOutlet",
    props: {
        name: { type: String, required: true },
        slotProps: {
            type: Object as PropType<Record<string, unknown>>,
            default: () => ({}),
        },
    },
    setup(props, { slots }) {
        const context = useTableContext();

        return () =>
            renderSlot(
                context.slots,
                props.name,
                { ...context.scope, ...props.slotProps },
                () => slots.default?.() ?? [],
            );
    },
});
