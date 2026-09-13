import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { render } from "./dist-ssr/ssr.js";

const root = process.argv[2];
function page(url, headers = {}) {
    return JSON.parse(
        execFileSync("php", [join(root, "tests/Consumer/page.php")], {
            input: JSON.stringify({ url, headers }),
            encoding: "utf8",
        }),
    );
}

const initial = page("/topics");
assert.equal(initial.component, "Topics/Index");
assert.equal(initial.props.topics.results.data.length, 3);
assert.equal(initial.props.topics.filters[0].lazyLoaded, false);
const output = await render(initial);
assert.match(output.body, /Alpha/);
assert.match(output.body, /data-slot="table"/);
assert.match(output.body, /Consumer topics/);

const search = page("/topics?table[topics][search]=Beta", {
    "x-inertia-partial-component": "Topics/Index",
    "x-inertia-partial-data": "topics",
});
assert.deepEqual(
    search.props.topics.results.data.map((row) => row.name),
    ["Beta"],
);
const sorted = page("/topics?table[topics][sort]=-name");
assert.equal(sorted.props.topics.results.data[0].name, "Gamma");
const lazy = page("/topics", {
    "x-musing-inertia-table-lazy-filters": JSON.stringify({
        topics: ["status"],
    }),
});
assert.equal(lazy.props.topics.filters[0].options.length, 2);
assert.equal(lazy.props.topics.filters[0].lazyLoaded, true);
const filtered = page(
    "/topics?table[topics][filters][status][enabled]=1&table[topics][filters][status][clause]=in&table[topics][filters][status][value][]=draft",
);
assert.deepEqual(
    filtered.props.topics.results.data.map((row) => row.name),
    ["Beta"],
);
assert.match((await render(filtered)).body, /Beta/);

const manifest = JSON.parse(readFileSync("dist/.vite/manifest.json", "utf8"));
const multiple = page("/multiple");
assert.equal(multiple.component, "Topics/Multiple");
assert.deepEqual(
    multiple.props.publishedTopics.results.data.map((row) => row.name),
    ["Alpha", "Gamma"],
);
assert.deepEqual(
    multiple.props.draftTopics.results.data.map((row) => row.name),
    ["Beta"],
);
const multipleHtml = (await render(multiple)).body;
assert.match(multipleHtml, /Published topics/);
assert.match(multipleHtml, /Draft topics/);
const multipleUrl =
    "/multiple?table[publishedTopics][search]=Gamma&table[draftTopics][search]=Beta&table[draftTopics][sort]=-name";
const both = page(multipleUrl);
assert.deepEqual(
    both.props.publishedTopics.results.data.map((row) => row.name),
    ["Gamma"],
);
assert.deepEqual(
    both.props.draftTopics.results.data.map((row) => row.name),
    ["Beta"],
);
const partial = page(multipleUrl, {
    "x-inertia-partial-component": "Topics/Multiple",
    "x-inertia-partial-data": "publishedTopics",
});
assert.equal(Object.hasOwn(partial.props, "draftTopics"), false);
assert.deepEqual(
    partial.props.publishedTopics.results.data.map((row) => row.name),
    ["Gamma"],
);
// Inertia merges partial props; verify the resulting page still renders both tables.
const mergedHtml = (
    await render({
        ...both,
        ...partial,
        props: { ...both.props, ...partial.props },
    })
).body;
assert.match(mergedHtml, /Gamma/);
assert.match(mergedHtml, /Beta/);
const headless = page("/headless");
assert.equal(headless.component, "Topics/Headless");
const headlessHtml = (await render(headless)).body;
assert.match(headlessHtml, /Headless topics/);
assert.match(headlessHtml, /Alpha/);
assert.match(headlessHtml, /Sort by name/);
const headlessSearch = page("/headless?table[topics][search]=Beta", {
    "x-inertia-partial-component": "Topics/Headless",
    "x-inertia-partial-data": "topics",
});
const headlessSearchHtml = (await render(headlessSearch)).body;
assert.match(headlessSearchHtml, /<td>Beta<\/td>/);
assert.doesNotMatch(headlessSearchHtml, /<td>Alpha<\/td>/);
const headlessEmpty = page("/headless?table[topics][search]=missing");
assert.match((await render(headlessEmpty)).body, /No matching topics/);
const css = manifest["main.ts"].css
    .map((path) => readFileSync(`dist/${path}`, "utf8"))
    .join("\n");
assert.match(css, /\.tb-wrapper|\.tb-table/);
assert.match(css, /\.inline-flex/);
console.log(
    "Real Laravel resources and Inertia/Vue SSR verified without browser globals or router mocks.",
);
