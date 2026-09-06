import { onScopeDispose } from "vue";

type PollingOptions<T> = {
    delay: number;
    parse: (response: Response) => Promise<T>;
    nextEndpoint: (value: T, endpoint: string) => string | null;
    onError: (reason: unknown) => void;
};

/** @internal Shared fixed-delay status polling for queued table operations. */
export function usePolling<T>(options: PollingOptions<T>) {
    let timer: number | null = null;
    let generation = 0;

    function stop() {
        generation += 1;

        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    function schedule(endpoint: string, currentGeneration: number) {
        timer = window.setTimeout(() => {
            void poll(endpoint, currentGeneration);
        }, options.delay);
    }

    async function poll(endpoint: string, currentGeneration: number) {
        try {
            const response = await fetch(endpoint, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (currentGeneration !== generation) return;

            const value = await options.parse(response);

            if (currentGeneration !== generation) return;

            const nextEndpoint = options.nextEndpoint(value, endpoint);

            if (currentGeneration !== generation) return;

            if (nextEndpoint) {
                schedule(nextEndpoint, currentGeneration);
            } else {
                stop();
            }
        } catch (reason) {
            if (currentGeneration !== generation) return;

            options.onError(reason);
            stop();
        }
    }

    function start(endpoint: string) {
        stop();
        schedule(endpoint, generation);
    }

    onScopeDispose(stop);

    return { start, stop };
}
