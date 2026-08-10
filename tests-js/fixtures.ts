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
                label: "Name",
                type: "text",
                searchable: true,
                sortable: true,
                toggleable: true,
            },
            {
                attribute: "is_featured",
                label: "Featured",
                type: "boolean",
                searchable: false,
                sortable: false,
                toggleable: true,
            },
        ],
        filters: [
            {
                attribute: "status",
                label: "Status",
                type: "select",
                options: { featured: "Featured" },
            },
        ],
        state: {
            search: "",
            sort: "name",
            filters: {},
            page: 1,
            perPage: 25,
        },
        results: {
            data: [
                { id: 1, name: "Alpha", is_featured: false },
                { id: 2, name: "Beta", is_featured: true },
            ],
            currentPage: 1,
            from: 1,
            lastPage: 2,
            links: [],
            perPage: 25,
            to: 2,
            total: 30,
        },
        reloadProps: ["featuredCount"],
        debounceTime: 300,
        perPageOptions: [10, 25, 50],
        ...overrides,
    };
}
