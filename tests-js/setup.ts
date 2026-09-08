import { disableAutoUnmount, enableAutoUnmount } from "@vue/test-utils";
import { nextTick } from "vue";
import { afterAll, afterEach, beforeEach, vi } from "vitest";
import { setIconResolver } from "../resources/js/icons";
import { resetInertiaMock } from "./inertiaMock";

beforeEach(() => resetInertiaMock());

// Dispose Vue scopes while their timers and browser mocks still exist.
enableAutoUnmount((unmount) => {
    afterEach(async () => {
        unmount();
        await nextTick();
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        vi.useRealTimers();
        setIconResolver(null);
        document.head.querySelector('meta[name="csrf-token"]')?.remove();
        document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
        document.body.replaceChildren();
    });
});

// Setup files run again for each file even when modules share a worker cache.
afterAll(() => disableAutoUnmount());
