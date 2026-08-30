import { router } from "@inertiajs/vue3";
import type { RequestPayload } from "@inertiajs/core";
import { computed, ref } from "vue";
import type { TableItem, TableView, TableViewState } from "./types";
import type { UseTable } from "./useTable";

export function useViews<T extends TableItem>(table: UseTable<T>) {
    const isMutating = ref(false);
    const resource = computed(() => table.resource.value.views ?? null);
    const selectedView = computed(() => {
        const selected = resource.value?.selected;

        return (
            resource.value?.items.find(
                (view) => String(view.id) === String(selected),
            ) ?? null
        );
    });

    function persistableState(): TableViewState {
        const state = table.state.value;
        const persisted: TableViewState = {
            schemaVersion: 1,
            sort: state.sort,
            filters: state.filters,
            columns: state.columns,
            pinnedColumns: state.pinnedColumns ?? { left: [], right: [] },
            perPage: state.perPage,
        };

        if (resource.value?.includeSearch) persisted.search = state.search;

        return persisted;
    }

    function canonical(value: unknown): string {
        if (Array.isArray(value)) {
            return `[${value.map(canonical).join(",")}]`;
        }

        if (value !== null && typeof value === "object") {
            return `{${Object.entries(value)
                .sort(([left], [right]) => left.localeCompare(right))
                .map(
                    ([key, child]) =>
                        `${JSON.stringify(key)}:${canonical(child)}`,
                )
                .join(",")}}`;
        }

        return JSON.stringify(value);
    }

    const isDirty = computed(() => {
        const selected = selectedView.value;

        return selected
            ? canonical(persistableState()) !== canonical(selected.state)
            : false;
    });

    function applyView(view: TableView) {
        const current = table.state.value;
        table.visit({
            ...current,
            sort: view.state.sort,
            filters: view.state.filters,
            columns: view.state.columns,
            pinnedColumns: view.state.pinnedColumns,
            perPage: view.state.perPage,
            search: resource.value?.includeSearch
                ? (view.state.search ?? "")
                : current.search,
            page: 1,
            view: view.id,
        });
    }

    function reset() {
        if (selectedView.value) applyView(selectedView.value);
    }

    function visitEndpoint(
        endpoint: string | null,
        method: "post" | "patch" | "delete",
        data: Record<string, unknown>,
    ) {
        if (!endpoint || isMutating.value) return;

        isMutating.value = true;
        router.visit(endpoint, {
            method,
            data: JSON.parse(JSON.stringify(data)) as RequestPayload,
            preserveScroll: true,
            only: [
                table.resource.value.name,
                ...table.resource.value.options.reloadProps,
            ],
            onFinish: () => {
                isMutating.value = false;
            },
        });
    }

    function create(name: string) {
        visitEndpoint(resource.value?.storeEndpoint ?? null, "post", {
            name,
            state: persistableState(),
        });
    }

    function rename(view: TableView, name: string) {
        visitEndpoint(view.endpoints.update, "patch", {
            name,
            version: view.version,
        });
    }

    function update(view: TableView) {
        visitEndpoint(view.endpoints.update, "patch", {
            state: persistableState(),
            version: view.version,
        });
    }

    function remove(view: TableView) {
        visitEndpoint(view.endpoints.delete, "delete", {
            version: view.version,
        });
    }

    function setDefault(view: TableView) {
        visitEndpoint(view.endpoints.default, "post", {
            version: view.version,
        });
    }

    function setShared(view: TableView, shared: boolean) {
        visitEndpoint(view.endpoints.share, "post", {
            shared,
            version: view.version,
        });
    }

    return {
        applyView,
        create,
        isDirty,
        isMutating,
        persistableState,
        remove,
        rename,
        reset,
        resource,
        selectedView,
        setDefault,
        setShared,
        update,
    };
}

export type UseViews<T extends TableItem = TableItem> = ReturnType<
    typeof useViews<T>
>;
