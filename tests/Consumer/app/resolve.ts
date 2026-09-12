import type { DefineComponent } from "vue";

// Type-only fixtures contain deliberately invalid expressions; never bundle them as pages.
const pages = import.meta.glob<{ default: DefineComponent }>("./Topics.vue");

export function resolve(name: string) {
    if (name !== "Topics/Index")
        throw new Error(`Unknown consumer page: ${name}`);
    return pages["./Topics.vue"]().then((page) => page.default);
}
