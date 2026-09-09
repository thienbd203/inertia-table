import { createInertiaApp } from "@inertiajs/vue3";
import { createApp, createSSRApp, h } from "vue";
import { resolve } from "./resolve";
import "@musing/inertia-table-vue/style.css";
import "./style.css";

void createInertiaApp({
    resolve,
    setup({ el, App, props, plugin }) {
        if (!el) throw new Error("Missing consumer mount element");
        const create = el.hasChildNodes() ? createSSRApp : createApp;
        return create({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
