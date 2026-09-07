import { vi } from "vitest";

export const inertiaVisit = vi.fn();
export const inertiaReload = vi.fn();
export const inertiaListeners = new Map<string, (...args: unknown[]) => void>();

let pageUrl = "/admin/topics";

export const Link = "a";

export const router = {
    visit: inertiaVisit,
    reload: inertiaReload,
    on: vi.fn((event: string, callback: (...args: unknown[]) => void) => {
        inertiaListeners.set(event, callback);

        return vi.fn();
    }),
};

export function usePage() {
    return { url: pageUrl };
}

export function resetInertiaMock(url = "/admin/topics") {
    pageUrl = url;
    inertiaVisit.mockReset();
    inertiaReload.mockReset();
    router.on.mockClear();
    inertiaListeners.clear();
}
