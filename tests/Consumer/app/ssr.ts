import type { Page } from "@inertiajs/core";
import { createInertiaApp } from "@inertiajs/vue3";
import { renderToString } from "@vue/server-renderer";
import { createSSRApp, h } from "vue";
import { resolve } from "./resolve";

export function render(page: Page) {
    return createInertiaApp({
        page,
        render: renderToString,
        resolve,
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin);
        },
    });
}
