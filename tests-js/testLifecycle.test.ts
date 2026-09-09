import { mount } from "@vue/test-utils";
import { defineComponent, h, onScopeDispose, ref } from "vue";
import { afterAll, expect, it, vi } from "vitest";
import { useTable } from "../resources/js/useTable";
import { topicResource } from "./fixtures";
import { inertiaVisit } from "./inertiaMock";

const activeScopes = new Set<number>();
const visitsAfterDisposal: number[] = [];

it.each([1, 2])("disposes mounted scopes between tests (%s)", (id) => {
    expect(activeScopes.size).toBe(0);
    vi.useFakeTimers({ toFake: ["setTimeout", "clearTimeout"] });

    mount(
        defineComponent({
            setup() {
                const table = useTable(ref(topicResource()));
                activeScopes.add(id);
                onScopeDispose(() => {
                    activeScopes.delete(id);
                    vi.advanceTimersByTime(1_000);
                    visitsAfterDisposal.push(inertiaVisit.mock.calls.length);
                });
                table.setSearch("pending search");
                return () => h("div");
            },
        }),
    );

    expect(activeScopes.size).toBe(1);
    expect(vi.getTimerCount()).toBeGreaterThan(0);
});

afterAll(() => {
    expect(activeScopes.size).toBe(0);
    expect(visitsAfterDisposal).toEqual([0, 0]);
});
