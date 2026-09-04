<script setup lang="ts" generic="T extends TableItem">
import { computed, nextTick, ref, toRef, useSlots, watch } from "vue";
import { Confirmation, QueuedActionDialog } from "@/components/table/actions";
import { FilterList } from "@/components/table/filters";
import { Pagination, Toolbar, Viewport } from "@/components/table/layout";
import { SlotOutlet } from "@/components/table/shared";
import { provideTableContext } from "@/context/tableContext";
import type { IconResolver } from "@/icons";
import {
    createTableI18n,
    provideTableI18n,
    useTableI18n,
    type TableMessageOverrides,
} from "@/i18n";
import "@/styles/data-table.css";
import type {
    TableAction,
    TableColumn,
    TableExport,
    TableItem,
    TableKey,
    TableResource,
    TableSelection,
    QueuedActionStatus,
    QueuedExportStatus,
} from "@/types";
import { useActions } from "@/useActions";
import { useExports } from "@/useExports";
import { useTable } from "@/useTable";
import { useStickyColumns } from "@/useStickyColumns";
import { useViews } from "@/useViews";

const props = withDefaults(
    defineProps<{
        resource: TableResource<T>;
        searchPlaceholder?: string;
        iconResolver?: IconResolver;
        locale?: string;
        messages?: TableMessageOverrides;
        rowKey?: (item: T, index: number) => TableKey;
    }>(),
    {},
);
const emit = defineEmits<{
    customAction: [
        action: TableAction,
        keys: TableKey[],
        onFinish: () => void,
        selection: TableSelection,
    ];
    actionSuccess: [
        action: TableAction,
        keys: TableKey[],
        selection: TableSelection,
    ];
    actionError: [
        action: TableAction,
        keys: TableKey[],
        error: unknown,
        selection: TableSelection,
    ];
    actionQueued: [
        action: TableAction,
        status: QueuedActionStatus,
        selection: TableSelection,
    ];
    actionProgress: [
        action: TableAction,
        status: QueuedActionStatus,
        selection: TableSelection,
    ];
    exportSuccess: [definition: TableExport];
    exportQueued: [definition: TableExport, status: QueuedExportStatus];
    exportError: [definition: TableExport, error: Error];
    rowClick: [item: T, column: TableColumn | null];
}>();
defineSlots<{
    [name: string]: ((props: any) => any) | undefined;
}>();

const resource = toRef(props, "resource");
const inheritedI18n = useTableI18n();
const i18n = createTableI18n(
    computed(() => props.locale ?? inheritedI18n.locale.value),
    computed(() => props.messages ?? {}),
    inheritedI18n,
);
provideTableI18n(i18n);
const table = useTable(resource);
const sticky = useStickyColumns(table);
const views = useViews(table);
const actions = useActions(
    table,
    { rowKey: props.rowKey },
    {
        onCustomAction: (action, keys, onFinish, selection) =>
            emit("customAction", action, keys, onFinish, selection),
        onSuccess: (action, keys, selection) =>
            emit("actionSuccess", action, keys, selection),
        onError: (action, keys, error, selection) =>
            emit("actionError", action, keys, error, selection),
        onQueued: (action, status, selection) =>
            emit("actionQueued", action, status, selection),
        onProgress: (action, status, selection) =>
            emit("actionProgress", action, status, selection),
    },
);
const tableExports = useExports(table, actions, {
    onSuccess: (definition) => emit("exportSuccess", definition),
    onQueued: (definition, status) => emit("exportQueued", definition, status),
    onError: (definition, error) => emit("exportError", definition, error),
});
function enabledFilterAttributes(resource: TableResource<T>) {
    return Object.entries(resource.state.filters)
        .filter(([, state]) => state.enabled)
        .map(([attribute]) => attribute);
}

const activeFilterAttributes = ref(enabledFilterAttributes(props.resource));
const pendingFilterPopover = ref<string | null>(null);

watch(
    () => enabledFilterAttributes(props.resource),
    (attributes) => {
        activeFilterAttributes.value = [
            ...new Set([...activeFilterAttributes.value, ...attributes]),
        ];
    },
);

async function addFilter(attribute: string) {
    if (activeFilterAttributes.value.includes(attribute)) {
        return;
    }

    activeFilterAttributes.value = [...activeFilterAttributes.value, attribute];

    // The dropdown must unmount before the popover mounts; otherwise its
    // dismissable layer considers the menu selection an outside interaction.
    await nextTick();
    pendingFilterPopover.value = attribute;

    const definition = props.resource.filters.find(
        (filter) => filter.attribute === attribute,
    );
    const clause = definition?.clauses[0];
    if (
        clause &&
        ["is_true", "is_false", "is_set", "is_not_set"].includes(clause)
    ) {
        table.setFilter(attribute, true, clause);
    }
}

function removeFilter(attribute: string) {
    activeFilterAttributes.value = activeFilterAttributes.value.filter(
        (candidate) => candidate !== attribute,
    );
    table.removeFilter(attribute);
}

function consumePendingFilterPopover(attribute: string) {
    if (pendingFilterPopover.value === attribute) {
        pendingFilterPopover.value = null;
    }
}

function clearFilters() {
    activeFilterAttributes.value = [];
    pendingFilterPopover.value = null;
    table.clearFilters();
}

provideTableContext({
    resource,
    table,
    sticky,
    actions,
    exports: tableExports,
    views,
    iconResolver: props.iconResolver,
    i18n,
    searchPlaceholder: computed(
        () => props.searchPlaceholder ?? i18n.t("searchPlaceholder"),
    ),
    slots: useSlots(),
    activeFilterAttributes,
    pendingFilterPopover,
    addFilter,
    consumePendingFilterPopover,
    removeFilter,
    clearFilters,
    scope: { table, sticky, actions, exports: tableExports, views },
});
</script>

<template>
    <div
        class="tb-wrapper relative"
        :aria-busy="table.isNavigating.value"
        :class="{ 'cursor-wait': table.isNavigating.value }"
    >
        <div
            class="transition-opacity duration-150"
            :class="{
                'pointer-events-none opacity-50': table.isNavigating.value,
            }"
        >
            <SlotOutlet name="topbar"><Toolbar /></SlotOutlet>
            <SlotOutlet name="filters"><FilterList /></SlotOutlet>
            <Viewport
                @row-click="
                    (item, column) => emit('rowClick', item as T, column)
                "
            />
            <SlotOutlet
                v-if="
                    resource.capabilities.paginated &&
                    (resource.results.data.length > 0 ||
                        resource.results.hasPreviousPage)
                "
                name="footer"
            >
                <Pagination />
            </SlotOutlet>
        </div>
        <SlotOutlet v-if="table.isNavigating.value" name="loading">
            <div class="absolute inset-0 z-10" role="status">
                <span class="sr-only">{{ i18n.t("loading") }}</span>
            </div>
        </SlotOutlet>
        <SlotOutlet
            v-if="actions.pendingAction.value"
            name="confirmation"
            :slot-props="{ pending: actions.pendingAction.value }"
        >
            <Confirmation />
        </SlotOutlet>
        <SlotOutlet
            v-if="actions.queuedAction.value || actions.actionError.value"
            name="queuedAction"
            :slot-props="{ status: actions.queuedAction.value }"
        >
            <QueuedActionDialog />
        </SlotOutlet>
    </div>
</template>
