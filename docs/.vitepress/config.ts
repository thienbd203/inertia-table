import { defineConfig } from "vitepress";

const repository = "https://github.com/thienbd203/inertia-table";
const base = "/inertia-table/";
const site = "https://thienbd203.github.io/inertia-table/";

export default defineConfig({
    lang: "en-US",
    title: "Musing Inertia Table",
    titleTemplate: ":title · Musing Inertia Table",
    description: "Server-driven data tables for Laravel, Inertia.js, and Vue.",
    base,
    cleanUrls: true,
    lastUpdated: true,
    srcExclude: ["implementation-plan.vi.md", "docs-site-plan.vi.md"],
    sitemap: {
        hostname: site,
    },
    head: [
        ["meta", { name: "theme-color", content: "#0f766e" }],
        ["meta", { property: "og:type", content: "website" }],
        ["meta", { property: "og:site_name", content: "Musing Inertia Table" }],
    ],
    themeConfig: {
        logo: {
            light: "/mark-light.svg",
            dark: "/mark-dark.svg",
            alt: "Musing Inertia Table",
        },
        nav: [
            { text: "Guide", link: "/guide/getting-started" },
            { text: "Features", link: "/features/actions" },
            { text: "API", link: "/reference/php-api" },
            {
                text: "Playground",
                link: "https://github.com/thienbd203/inertia-table-playground",
            },
        ],
        sidebar: [
            {
                text: "Getting started",
                items: [
                    { text: "Introduction", link: "/" },
                    { text: "Getting started", link: "/guide/getting-started" },
                    { text: "Configuration", link: "/guide/configuration" },
                    {
                        text: "Table definitions",
                        link: "/guide/table-definitions",
                    },
                ],
            },
            {
                text: "Guides",
                items: [
                    { text: "Columns", link: "/guide/columns" },
                    {
                        text: "Search and filters",
                        link: "/guide/search-and-filters",
                    },
                    { text: "Relationships", link: "/guide/relationships" },
                    {
                        text: "Pagination and URL state",
                        link: "/guide/pagination-and-url-state",
                    },
                    { text: "Multiple tables", link: "/guide/multiple-tables" },
                ],
            },
            {
                text: "Features",
                items: [
                    { text: "Summaries", link: "/features/summaries" },
                    { text: "Actions", link: "/features/actions" },
                    { text: "Queues", link: "/features/queues" },
                    { text: "Exports", link: "/features/exports" },
                    { text: "Saved views", link: "/features/saved-views" },
                ],
            },
            {
                text: "Customization",
                items: [
                    {
                        text: "Translations",
                        link: "/customization/translations",
                    },
                    {
                        text: "Rendering and slots",
                        link: "/customization/rendering-and-slots",
                    },
                    { text: "Styling", link: "/customization/styling" },
                    {
                        text: "Headless API",
                        link: "/customization/headless-api",
                    },
                ],
            },
            {
                text: "Reference",
                items: [
                    { text: "PHP API", link: "/reference/php-api" },
                    { text: "Vue API", link: "/reference/vue-api" },
                    {
                        text: "Resource schema",
                        link: "/reference/resource-schema",
                    },
                    { text: "Configuration", link: "/reference/configuration" },
                    {
                        text: "Artisan commands",
                        link: "/reference/artisan-commands",
                    },
                    { text: "Compatibility", link: "/reference/compatibility" },
                ],
            },
            {
                text: "Maintainers",
                collapsed: true,
                items: [
                    { text: "Architecture", link: "/internals/architecture" },
                    { text: "API stability", link: "/internals/api-stability" },
                    { text: "Development", link: "/internals/development" },
                    { text: "Releasing", link: "/internals/releasing" },
                ],
            },
        ],
        search: {
            provider: "local",
        },
        socialLinks: [{ icon: "github", link: repository }],
        editLink: {
            pattern: `${repository}/edit/master/docs/:path`,
            text: "Edit this page on GitHub",
        },
        footer: {
            message: "Released under the MIT License.",
            copyright: "Copyright © Musing Inertia Table contributors",
        },
        outline: {
            level: [2, 3],
        },
    },
});
