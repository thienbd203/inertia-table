import { effectScope } from "vue";
import { describe, expect, it, vi } from "vitest";
import { usePolling } from "../resources/js/usePolling";

describe("usePolling", () => {
    it("ignores an in-flight response after a newer poll replaces it", async () => {
        vi.useFakeTimers();
        let resolveFirst!: (response: Response) => void;
        const first = new Promise<Response>((resolve) => {
            resolveFirst = resolve;
        });
        const fetch = vi
            .fn()
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ id: "new" }), {
                    headers: { "content-type": "application/json" },
                }),
            );
        vi.stubGlobal("fetch", fetch);
        const received: string[] = [];
        const scope = effectScope();
        let polling!: ReturnType<typeof usePolling<{ id: string }>>;

        scope.run(() => {
            polling = usePolling({
                delay: 1_500,
                parse: (response) => response.json() as Promise<{ id: string }>,
                nextEndpoint(value) {
                    received.push(value.id);

                    return null;
                },
                onError: vi.fn(),
            });
        });

        polling.start("/old");
        await vi.advanceTimersByTimeAsync(1_500);
        polling.start("/new");
        resolveFirst(new Response(JSON.stringify({ id: "old" })));
        await Promise.resolve();
        await vi.advanceTimersByTimeAsync(1_500);

        expect(received).toEqual(["new"]);
        expect(fetch).toHaveBeenNthCalledWith(1, "/old", expect.anything());
        expect(fetch).toHaveBeenNthCalledWith(2, "/new", expect.anything());
        scope.stop();
    });
});
