export type TableKey = string | number;

export type TableItem = {
    id?: TableKey;
    _table?: {
        url: string | null;
        columns: Record<string, string>;
        cells?: Record<string, Record<string, unknown>>;
        actions: TableAction[];
    };
};

export type TableColumn = {
    attribute: string;
    header: string;
    type: "text" | "numeric" | "boolean" | "date" | "action" | string;
    sortable: boolean;
    toggleable: boolean;
    visibleByDefault: boolean;
    alignment: "left" | "center" | "right";
    wrap?: boolean;
    truncate?: number | null;
    tooltip?: string | null;
    headerClass?: string | null;
    cellClass?: string | null;
    trueLabel?: string;
    falseLabel?: string;
    trueIcon?: string | null;
    falseIcon?: string | null;
    meta: Record<string, unknown>;
};

export type TableFilterOption = {
    value: string | number | boolean;
    label: string;
};

export type TableFilter = {
    attribute: string;
    label: string;
    type: "text" | "set" | "select" | "numeric" | "date" | "boolean" | string;
    clauses: string[];
    options: TableFilterOption[];
    multiple?: boolean;
    hasDefaultValue?: boolean;
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
    icon: string | null;
    labelHidden: boolean;
    tooltip: string | null;
    confirmation: null | {
        title: string;
        message: string;
        confirmLabel: string;
        cancelLabel: string;
    };
    endpoint: {
        method: "get" | "post" | "patch" | "delete";
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
    search: string[];
    capabilities: {
        searchable: boolean;
        selectable: boolean;
        paginated: boolean;
        hasSearch?: boolean;
        hasFilters?: boolean;
        hasActions?: boolean;
        hasBulkActions?: boolean;
        hasToggleableColumns?: boolean;
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
