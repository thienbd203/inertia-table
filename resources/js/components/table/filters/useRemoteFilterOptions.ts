import {
    computed,
    onScopeDispose,
    ref,
    toValue,
    watch,
    type MaybeRefOrGetter,
} from "vue";
import { useTableContext } from "@/context/tableContext";
import { csrfHeaders, responseMessage } from "@/http";
import type { TableFilter, TableFilterOption } from "@/types";

type RemoteOptionResponse = {
    options: TableFilterOption[];
    selected: TableFilterOption[];
    nextCursor: string | null;
};

type CacheEntry = {
    expiresAt: number;
    response: RemoteOptionResponse;
};

const responseCache = new Map<string, CacheEntry>();
const labelCache = new Map<
    string,
    { expiresAt: number; option: TableFilterOption }
>();

function stableStringify(value: unknown): string {
    if (Array.isArray(value)) {
        return `[${value.map(stableStringify).join(",")}]`;
    }

    if (value && typeof value === "object") {
        return `{${Object.entries(value as Record<string, unknown>)
            .sort(([left], [right]) => left.localeCompare(right))
            .map(
                ([key, entry]) =>
                    `${JSON.stringify(key)}:${stableStringify(entry)}`,
            )
            .join(",")}}`;
    }

    return JSON.stringify(value);
}

function mergeOptions(...groups: TableFilterOption[][]): TableFilterOption[] {
    const merged = new Map<string, TableFilterOption>();

    for (const option of groups.flat()) {
        merged.set(String(option.value), option);
    }

    return [...merged.values()];
}

function pruneCache(maxEntries: number): void {
    const now = Date.now();

    for (const [key, entry] of responseCache) {
        if (entry.expiresAt <= now) responseCache.delete(key);
    }
    for (const [key, entry] of labelCache) {
        if (entry.expiresAt <= now) labelCache.delete(key);
    }

    while (responseCache.size > maxEntries) {
        responseCache.delete(responseCache.keys().next().value!);
    }
    while (labelCache.size > maxEntries * 4) {
        labelCache.delete(labelCache.keys().next().value!);
    }
}

export function clearRemoteFilterOptionsCache(): void {
    responseCache.clear();
    labelCache.clear();
}

export function useRemoteFilterOptions(
    filter: MaybeRefOrGetter<TableFilter>,
    modelValue: MaybeRefOrGetter<unknown>,
) {
    const { i18n, resource } = useTableContext();
    const options = ref<TableFilterOption[]>([...toValue(filter).options]);
    const search = ref("");
    const loading = ref(false);
    const loadingMore = ref(false);
    const error = ref<string | null>(null);
    const nextCursor = ref<string | null>(null);
    let controller: AbortController | null = null;
    let requestSequence = 0;
    let debounceTimer: ReturnType<typeof setTimeout> | undefined;

    const selectedValues = computed(() => {
        const value = toValue(modelValue);
        const values = Array.isArray(value) ? value : [value];

        return values.filter(
            (candidate): candidate is string | number | boolean =>
                ["string", "number", "boolean"].includes(typeof candidate) &&
                String(candidate) !== "",
        );
    });
    const dependencyState = computed(() => {
        const definition = toValue(filter);

        return Object.fromEntries(
            (definition.remote?.dependsOn ?? []).map((attribute) => [
                attribute,
                resource.value.state.filters[attribute] ?? null,
            ]),
        );
    });
    const dependencyIdentity = computed(() =>
        stableStringify(dependencyState.value),
    );

    function rememberedSelectedOptions(): TableFilterOption[] {
        const definition = toValue(filter);
        const now = Date.now();

        return selectedValues.value.flatMap((value) => {
            const entry = labelCache.get(
                `${definition.remote?.endpoint}:${String(value)}`,
            );

            return entry && entry.expiresAt > now ? [entry.option] : [];
        });
    }

    function rememberLabels(next: TableFilterOption[]): void {
        const definition = toValue(filter);
        const remote = definition.remote;
        if (!remote) return;

        const expiresAt = Date.now() + Math.max(0, remote.cacheTtl);
        for (const option of next) {
            labelCache.set(`${remote.endpoint}:${String(option.value)}`, {
                expiresAt,
                option,
            });
        }
    }

    function applyResponse(
        response: RemoteOptionResponse,
        append: boolean,
    ): void {
        const initial = toValue(filter).options;
        const remembered = rememberedSelectedOptions();
        const current = append ? options.value : [];
        rememberLabels([...response.options, ...response.selected, ...initial]);
        options.value = mergeOptions(
            initial,
            remembered,
            response.selected,
            current,
            response.options,
        );
        nextCursor.value = response.nextCursor;
    }

    async function load(
        cursor: string | null = null,
        append = false,
        force = false,
    ): Promise<void> {
        const definition = toValue(filter);
        const remote = definition.remote;
        if (!remote) return;

        const sequence = ++requestSequence;
        controller?.abort();
        controller = new AbortController();
        error.value = null;
        if (append) loadingMore.value = true;
        else loading.value = true;

        const cacheKey = stableStringify({
            endpoint: remote.endpoint,
            search: search.value.trim(),
            dependencies: dependencyState.value,
            selected: selectedValues.value.map(String).sort(),
            cursor,
        });
        const cached = responseCache.get(cacheKey);
        if (!force && cached && cached.expiresAt > Date.now()) {
            applyResponse(cached.response, append);
            loading.value = false;
            loadingMore.value = false;
            return;
        }

        try {
            const response = await fetch(remote.endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    ...(csrfHeaders() ?? {}),
                },
                body: JSON.stringify({
                    search: search.value.trim(),
                    cursor,
                    selected: selectedValues.value,
                    perPage: remote.perPage,
                    state: {
                        search: resource.value.state.search,
                        sort: resource.value.state.sort,
                        filters: resource.value.state.filters,
                    },
                }),
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(
                    await responseMessage(response, i18n.t("optionLoadFailed")),
                );
            }

            const payload = (await response.json()) as RemoteOptionResponse;
            if (sequence !== requestSequence) return;

            const normalized: RemoteOptionResponse = {
                options: Array.isArray(payload.options) ? payload.options : [],
                selected: Array.isArray(payload.selected)
                    ? payload.selected
                    : [],
                nextCursor:
                    typeof payload.nextCursor === "string"
                        ? payload.nextCursor
                        : null,
            };
            responseCache.set(cacheKey, {
                expiresAt: Date.now() + Math.max(0, remote.cacheTtl),
                response: normalized,
            });
            pruneCache(Math.max(1, remote.maxCacheEntries));
            applyResponse(normalized, append);
        } catch (reason) {
            if (
                sequence === requestSequence &&
                !(
                    reason instanceof DOMException &&
                    reason.name === "AbortError"
                )
            ) {
                error.value =
                    reason instanceof Error
                        ? reason.message
                        : i18n.t("optionLoadFailed");
            }
        } finally {
            if (sequence === requestSequence) {
                loading.value = false;
                loadingMore.value = false;
            }
        }
    }

    function loadMore(): Promise<void> {
        return nextCursor.value
            ? load(nextCursor.value, true)
            : Promise.resolve();
    }

    function retry(): Promise<void> {
        return load(null, false, true);
    }

    watch(
        [() => toValue(filter).remote?.endpoint, dependencyIdentity],
        () => void load(),
        { immediate: true },
    );
    watch(search, () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
            () => void load(),
            toValue(filter).remote?.debounceTime ?? 250,
        );
    });
    watch(
        [() => toValue(filter).options, selectedValues],
        () => {
            options.value = mergeOptions(
                toValue(filter).options,
                rememberedSelectedOptions(),
                options.value,
            );
        },
        { deep: true },
    );

    onScopeDispose(() => {
        clearTimeout(debounceTimer);
        controller?.abort();
    });

    return {
        error,
        loadMore,
        loading,
        loadingMore,
        nextCursor,
        options,
        retry,
        search,
    };
}
