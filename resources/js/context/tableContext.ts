import { inject, provide, type InjectionKey, type Ref, type Slots } from "vue";
import type { IconResolver } from "@/icons";
import type { TableI18n } from "@/i18n";
import type { TableItem, TableResource } from "@/types";
import type { UseActions } from "@/useActions";
import type { UseExports } from "@/useExports";
import type { UseTable } from "@/useTable";
import type { UseStickyColumns } from "@/useStickyColumns";
import type { UseViews } from "@/useViews";

export type TableContext<T extends TableItem = TableItem> = {
    resource: Ref<TableResource<T>>;
    table: UseTable<T>;
    sticky: UseStickyColumns<T>;
    actions: UseActions<T>;
    exports: UseExports<T>;
    views: UseViews<T>;
    iconResolver?: IconResolver;
    i18n: TableI18n;
    searchPlaceholder: Ref<string>;
    slots: Slots;
    activeFilterAttributes: Ref<string[]>;
    pendingFilterPopover: Ref<string | null>;
    addFilter: (attribute: string) => void;
    consumePendingFilterPopover: (attribute: string) => void;
    removeFilter: (attribute: string) => void;
    clearFilters: () => void;
    scope: {
        table: UseTable<T>;
        sticky: UseStickyColumns<T>;
        actions: UseActions<T>;
        exports: UseExports<T>;
        views: UseViews<T>;
    };
};

const tableContextKey = Symbol("ToolbeltTableContext") as InjectionKey<
    TableContext<TableItem>
>;

export function provideTableContext<T extends TableItem>(
    context: TableContext<T>,
): void {
    provide(tableContextKey, context as unknown as TableContext<TableItem>);
}

export function useTableContext<
    T extends TableItem = TableItem,
>(): TableContext<T> {
    const context = inject(tableContextKey);

    if (!context) {
        throw new Error("Table components must be rendered inside DataTable.");
    }

    return context as unknown as TableContext<T>;
}
