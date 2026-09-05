<script setup lang="ts">
import { UiTableCell } from "@/components/ui/table";
import { useTableContext } from "@/context/tableContext";
import { formatSummaryValue } from "@/helpers/summaries";
import type { TableColumn } from "@/types";
import { SlotOutlet } from "../shared";

defineProps<{ canSelect: boolean }>();
const { resource, table, sticky, i18n } = useTableContext();

function alignmentClass(alignment: "left" | "center" | "right"): string {
    return {
        left: "text-left",
        center: "text-center",
        right: "text-right",
    }[alignment];
}

function valueFor(column: TableColumn): unknown {
    return resource.value.summaries?.[column.attribute];
}

function formattedValue(column: TableColumn): string {
    return formatSummaryValue(
        valueFor(column),
        column.summary?.format,
        i18n.locale.value,
    );
}
</script>

<template>
    <tfoot
        data-slot="table-footer"
        class="tb-summary-footer"
        :data-sticky-footer="resource.options.stickyFooter || undefined"
    >
        <tr data-slot="table-row" class="tb-summary-row">
            <UiTableCell
                v-if="canSelect"
                class="tb-selection-cell"
                :class="{
                    'tb-sticky-cell': sticky.selectionPinned.value,
                    'tb-sticky-footer-cell': resource.options.stickyFooter,
                }"
                :data-sticky-side="
                    sticky.selectionPinned.value ? 'left' : undefined
                "
                :style="sticky.style(sticky.selectionColumn)"
            />
            <UiTableCell
                v-for="column in table.visibleColumns.value"
                :key="column.attribute"
                :data-column="column.attribute"
                :data-alignment="column.alignment"
                :data-sticky-side="
                    sticky.pinSide(column.attribute) ?? undefined
                "
                :data-sticky-edge="sticky.edge(column.attribute) ?? undefined"
                :class="[
                    alignmentClass(column.alignment),
                    {
                        'tb-sticky-cell': sticky.pinSide(column.attribute),
                        'tb-sticky-footer-cell': resource.options.stickyFooter,
                    },
                ]"
                :style="[
                    table.columnStyle(column.attribute),
                    sticky.style(column.attribute),
                ]"
            >
                <SlotOutlet
                    :name="`summary(${column.attribute})`"
                    :slot-props="{
                        column,
                        definition: column.summary,
                        value: valueFor(column),
                        formatted: formattedValue(column),
                    }"
                >
                    <span v-if="column.summary" class="tb-summary-value">
                        <template v-if="table.isNavigating.value">
                            <span aria-hidden="true">…</span>
                            <span class="sr-only">{{ i18n.t("loading") }}</span>
                        </template>
                        <template v-else>{{ formattedValue(column) }}</template>
                    </span>
                </SlotOutlet>
            </UiTableCell>
        </tr>
    </tfoot>
</template>
