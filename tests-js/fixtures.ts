import type { TableItem, TableResource } from "../resources/js/types";

export type Topic = TableItem & {
    id: number;
    name: string;
    is_featured: boolean;
};

export function topicResource(
    overrides: Partial<TableResource<Topic>> = {},
): TableResource<Topic> {
    return {
        schemaVersion: 1,
        name: "topics",
        columns: [
            {
                attribute: "name",
                header: "Name",
                type: "text",
                sortable: true,
                toggleable: true,
                visibleByDefault: true,
                alignment: "left",
                meta: {},
            },
            {
                attribute: "is_featured",
                header: "Featured",
                type: "boolean",
                sortable: false,
                toggleable: true,
                visibleByDefault: true,
                alignment: "center",
                meta: {},
            },
            {
                attribute: "__actions",
                header: "Actions",
                type: "action",
                sortable: false,
                toggleable: false,
                visibleByDefault: true,
                alignment: "right",
                meta: {},
            },
        ],
        filters: [
            {
                attribute: "status",
                label: "Status",
                type: "select",
                clauses: ["equals"],
                options: [{ value: "featured", label: "Featured" }],
                meta: {},
            },
        ],
        actions: [
            {
                key: "delete",
                label: "Delete",
                scope: "bulk",
                authorized: true,
                disabled: false,
                hidden: false,
                variant: "destructive",
                icon: null,
                labelHidden: false,
                tooltip: null,
                buttonClass: null,
                disabledTooltip: null,
                confirmation: null,
                endpoint: { method: "delete", url: "/topics/bulk" },
                meta: {},
            },
        ],
        search: ["name"],
        capabilities: {
            searchable: true,
            selectable: true,
            paginated: true,
            hasSearch: true,
            hasFilters: true,
            hasActions: true,
            hasBulkActions: true,
            hasToggleableColumns: true,
        },
        state: {
            search: "",
            sort: "name",
            filters: {
                status: {
                    enabled: false,
                    clause: "equals",
                    value: null,
                },
            },
            columns: { name: true, is_featured: true, __actions: true },
            page: 1,
            perPage: 25,
        },
        results: {
            data: [
                {
                    id: 1,
                    name: "Alpha",
                    is_featured: false,
                    _table: {
                        key: 1,
                        url: "/topics/1",
                        columns: { name: "/topics/1" },
                        actions: [
                            {
                                key: "edit",
                                label: "Edit",
                                scope: "row",
                                authorized: true,
                                disabled: false,
                                hidden: false,
                                variant: "default",
                                icon: null,
                                labelHidden: false,
                                tooltip: null,
                                buttonClass: null,
                                disabledTooltip: null,
                                confirmation: null,
                                endpoint: {
                                    method: "get",
                                    url: "/topics/1",
                                },
                                meta: {},
                            },
                        ],
                    },
                },
                {
                    id: 2,
                    name: "Beta",
                    is_featured: true,
                    _table: { key: 2, url: null, columns: {}, actions: [] },
                },
            ],
            currentPage: 1,
            from: 1,
            lastPage: 2,
            links: [],
            perPage: 25,
            to: 2,
            total: 30,
        },
        options: {
            reloadProps: ["featuredCount"],
            debounceTime: 300,
            perPage: [10, 25, 50],
        },
        ...overrides,
    };
}
