import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    define: { __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: true },
    plugins: [vue(), tailwindcss()],
    build: {
        manifest: true,
        rollupOptions: { input: "main.ts" },
    },
    ssr: { noExternal: ["@musing/inertia-table-vue"] },
});
