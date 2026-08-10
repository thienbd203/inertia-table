import vue from "@vitejs/plugin-vue";
import dts from "vite-plugin-dts";
import { defineConfig } from "vitest/config";

export default defineConfig({
    plugins: [vue(), dts({ include: ["resources/js"] })],
    build: {
        lib: {
            entry: "resources/js/index.ts",
            formats: ["es"],
            fileName: "index",
            cssFileName: "inertia-table",
        },
        rollupOptions: {
            external: ["vue", "@inertiajs/vue3", "@lucide/vue", "reka-ui"],
        },
    },
    test: {
        environment: "happy-dom",
        include: ["tests-js/**/*.test.ts"],
    },
});
