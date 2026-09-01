import { computed } from "vue";
import { useTableContext } from "@/context/tableContext";
import { cellMeta, cellValue, displayValue } from "@/helpers/cells";
import { resolveIcon } from "@/icons";
import type { CellPresentationProps } from "./types";

export function useCellPresentation(props: CellPresentationProps) {
    const { iconResolver, i18n } = useTableContext();
    const value = computed(() => cellValue(props.item, props.column.attribute));
    const meta = computed(() => cellMeta(props.item, props.column.attribute));
    const display = computed(() =>
        displayValue(props.item, props.column, {
            trueLabel: i18n.t("yes"),
            falseLabel: i18n.t("no"),
        }),
    );
    const iconName = computed(() => {
        if (props.column.type === "boolean") {
            return value.value ? props.column.trueIcon : props.column.falseIcon;
        }

        return typeof meta.value.icon === "string" ? meta.value.icon : null;
    });
    const resolveNamedIcon = (name: string | null | undefined) =>
        name
            ? resolveIcon(
                  name,
                  {
                      column: props.column,
                      item: props.item,
                      value: value.value,
                  },
                  iconResolver,
              )
            : null;
    const icon = computed(() => resolveNamedIcon(iconName.value));

    return { display, icon, meta, resolveNamedIcon, value };
}
