import type { DefineComponent } from "vue";

// Type-only fixtures contain deliberately invalid expressions; never bundle them as pages.
const pages = import.meta.glob<{ default: DefineComponent }>([
    "./Topics.vue",
    "./Headless.vue",
    "./Multiple.vue",
]);

export function resolve(name: string) {
    const path =
        name === "Topics/Index"
            ? "./Topics.vue"
            : name === "Topics/Headless"
              ? "./Headless.vue"
              : name === "Topics/Multiple"
                ? "./Multiple.vue"
                : null;
    if (!path) throw new Error(`Unknown consumer page: ${name}`);
    return pages[path]().then((page) => page.default);
}
