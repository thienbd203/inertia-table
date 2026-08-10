export type TableKey = string | number;

export type TableItem = {
    id?: TableKey;
};

export type TableColumn = {
    attribute: string;
    label: string;
    type: "text" | "number" | "boolean" | "date" | string;
    searchable: boolean;
    sortable: boolean;
    toggleable: boolean;
};

export type TableFilterOption = string | number;

export type TableFilter = {
    attribute: string;
    label: string;
    type: "text" | "select" | "boolean" | string;
    options?: Record<string, string> | string[];
};

export type TableState = {
    search: string;
    sort: string | null;
    filters: Record<string, unknown>;
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
    state: TableState;
    results: TableResults<T>;
    reloadProps: string[];
    debounceTime: number;
    perPageOptions: number[];
};

export type DataTableOptions<T extends TableItem> = {
    rowKey?: (item: T, index: number) => TableKey;
};
