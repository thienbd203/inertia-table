import { inject, provide, type InjectionKey, type Ref, type Slots } from "vue";
import type { IconResolver } from "../icons";
import type { TableItem, TableResource } from "../types";
import type { UseActions } from "../useActions";
import type { UseTable } from "../useTable";

export type TableContext<T extends TableItem = TableItem> = {
    resource: Ref<TableResource<T>>;
    table: UseTable<T>;
    actions: UseActions<T>;
    iconResolver?: IconResolver;
    searchPlaceholder: Ref<string>;
    slots: Slots;
    scope: {
        table: UseTable<T>;
        actions: UseActions<T>;
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
