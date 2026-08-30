export type TableKey = string | number;

export type TableItem = {
    id?: TableKey;
    _table?: {
        key?: TableKey;
        selectable?: boolean;
        url: TableUrl | string | null;
        columns: Record<string, TableUrl | string>;
        cells?: Record<string, Record<string, unknown>>;
        actions: TableAction[];
    };
};

export type TableUrl = {
    url: string;
    preserveScroll: boolean;
    preserveState: boolean;
    newTab: boolean;
    download: boolean;
    disabled: boolean;
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
    asDropdown?: boolean;
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
    compactDisplayLabel?: string | null;
    showClause?: boolean;
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
    disabled: boolean;
    hidden: boolean;
    variant: "default" | "destructive";
    icon: string | null;
    labelHidden: boolean;
    tooltip: string | null;
    buttonClass: string | null;
    disabledTooltip: string | null;
    confirmation: null | {
        title: string | [string, string, string?];
        message: string | [string, string, string?];
        confirmLabel: string;
        cancelLabel: string;
    };
    endpoint: {
        method: "get" | "post" | "put" | "patch" | "delete";
        url: string;
    } | null;
    meta: Record<string, unknown>;
};

export type TableState = {
    search: string;
    sort: string | null;
    filters: Record<string, TableFilterState>;
    columns: Record<string, boolean>;
    page: number;
    perPage: number;
    view?: TableKey | null;
};

export type TableViewState = {
    schemaVersion: 1;
    sort: string | null;
    filters: Record<string, TableFilterState>;
    columns: Record<string, boolean>;
    pinnedColumns: { left: string[]; right: string[] };
    perPage: number;
    search?: string;
};

export type TableView = {
    id: TableKey;
    name: string;
    state: TableViewState;
    isDefault: boolean;
    isShared: boolean;
    version: number;
    canUpdate: boolean;
    canDelete: boolean;
    canShare: boolean;
    canDefault: boolean;
    endpoints: {
        update: string | null;
        delete: string | null;
        default: string | null;
        share: string | null;
    };
};

export type TableViewsResource = {
    items: TableView[];
    selected: TableKey | null;
    includeSearch: boolean;
    canCreate: boolean;
    storeEndpoint: string | null;
};

export type TableSelection = {
    all: boolean;
    keys: TableKey[];
    except: TableKey[];
    table: string;
    state: Pick<TableState, "search" | "filters">;
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
    selectableTotal?: number;
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
    views?: TableViewsResource | null;
};

export type TableOptions<T extends TableItem> = {
    rowKey?: (item: T, index: number) => TableKey;
};

export type DataTableOptions<T extends TableItem> = TableOptions<T>;
