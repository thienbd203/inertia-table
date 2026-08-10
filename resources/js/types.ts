export type TableKey = string | number;

export type TableItem = {
    id?: TableKey;
};

export type TableColumn = {
    attribute: string;
    header: string;
    type: "text" | "numeric" | "boolean" | "date" | "action" | string;
    sortable: boolean;
    toggleable: boolean;
    visibleByDefault: boolean;
    alignment: "left" | "center" | "right";
    meta: Record<string, unknown>;
};

export type TableFilterOption = {
    value: string | number | boolean;
    label: string;
};

export type TableFilter = {
    attribute: string;
    label: string;
    type: "text" | "select" | "boolean" | string;
    clauses: string[];
    options: TableFilterOption[];
    meta: Record<string, unknown>;
};

export type TableFilterState = {
    enabled: boolean;
    clause: string;
    value: unknown;
};

export type TableAction = {
    key: string;
    label: string;
    scope: "row" | "bulk" | "both";
    authorized: boolean;
    variant: "default" | "destructive";
    confirmation: null | {
        title: string;
        message: string;
        confirmLabel: string;
        cancelLabel: string;
    };
    endpoint: {
        method: "post" | "patch" | "delete";
        url: string;
    };
    meta: Record<string, unknown>;
};

export type TableState = {
    search: string;
    sort: string | null;
    filters: Record<string, TableFilterState>;
    columns: Record<string, boolean>;
    page: number;
    perPage: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type TableResults<T extends TableItem> = {
    data: T[];
    currentPage: number;
    from: number | null;
    lastPage: number;
    links: PaginationLink[];
    perPage: number;
    to: number | null;
    total: number;
};

export type TableResource<T extends TableItem = TableItem> = {
    schemaVersion: 1;
    name: string;
    columns: TableColumn[];
    filters: TableFilter[];
    actions: TableAction[];
    capabilities: {
        searchable: boolean;
        selectable: boolean;
        paginated: boolean;
    };
    state: TableState;
    results: TableResults<T>;
    options: {
        debounceTime: number;
        perPage: number[];
        reloadProps: string[];
    };
};

export type TableOptions<T extends TableItem> = {
    rowKey?: (item: T, index: number) => TableKey;
};

export type DataTableOptions<T extends TableItem> = TableOptions<T>;
