import type { DefineComponent } from "vue";

const pages = import.meta.glob<{ default: DefineComponent }>("./*.vue");

export function resolve(name: string) {
    if (name !== "Topics/Index")
        throw new Error(`Unknown consumer page: ${name}`);
    return pages["./Topics.vue"]().then((page) => page.default);
}
