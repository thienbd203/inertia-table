import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import {
    cpSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const fixture = dirname(fileURLToPath(import.meta.url));
const root = join(fixture, "../..");
const artifacts = join(root, "build/consumer");
mkdirSync(artifacts, { recursive: true });

function run(command, args, cwd = root) {
    execFileSync(command, args, { cwd, stdio: "inherit" });
}

run("php", ["vendor/bin/testbench", "package:discover", "--no-ansi"]);
const phpSmoke = execFileSync("php", [join(fixture, "verify-php.php")], {
    cwd: root,
    encoding: "utf8",
});
// Testbench's console exception renderer may return zero after a PHP failure.
assert.match(
    phpSmoke,
    /PHP checkout discovery, config and generated table verified\./,
);
console.log(phpSmoke.trim());
run("npm", ["run", "build"]);
const [packed] = JSON.parse(
    execFileSync(
        "npm",
        ["pack", "--ignore-scripts", "--json", "--pack-destination", artifacts],
        { cwd: root, encoding: "utf8" },
    ),
);
assert(packed.files.some(({ path }) => path === "dist/index.d.ts"));
assert(packed.files.some(({ path }) => path === "dist/inertia-table.css"));
assert(
    !packed.files.some(({ path }) =>
        /^(docs|tests|tests-js|build)\//.test(path),
    ),
);

const consumer = mkdtempSync(join(tmpdir(), "inertia-table-consumer-"));
cpSync(join(fixture, "app"), consumer, { recursive: true });
const installedVersion = (name) =>
    JSON.parse(
        readFileSync(join(root, "node_modules", name, "package.json"), "utf8"),
    ).version;
const dependencies = Object.fromEntries(
    [
        "@inertiajs/vue3",
        "@lucide/vue",
        "reka-ui",
        "tailwindcss",
        "vue",
        "@vitejs/plugin-vue",
        "vite",
        "typescript",
        "vue-tsc",
    ].map((name) => [name, installedVersion(name)]),
);
dependencies["@tailwindcss/vite"] = installedVersion("tailwindcss");
dependencies[packed.name] = `file:${join(artifacts, packed.filename)}`;
writeFileSync(
    join(consumer, "package.json"),
    JSON.stringify({ private: true, type: "module", dependencies }, null, 2),
);
writeFileSync(
    join(artifacts, "latest.json"),
    JSON.stringify({ consumer, root }, null, 2),
);
console.log(`Consumer: ${consumer}`);
run(
    "npm",
    [
        "install",
        "--ignore-scripts",
        "--no-audit",
        "--no-fund",
        "--strict-peer-deps",
        ...(process.argv.includes("--offline") ? ["--offline"] : []),
    ],
    consumer,
);
run("node", ["node_modules/vue-tsc/bin/vue-tsc.js", "--noEmit"], consumer);
run("node", ["node_modules/vite/bin/vite.js", "build"], consumer);
run(
    "node",
    [
        "node_modules/vite/bin/vite.js",
        "build",
        "--ssr",
        "ssr.ts",
        "--outDir",
        "dist-ssr",
    ],
    consumer,
);
cpSync(join(fixture, "verify.mjs"), join(consumer, "verify.mjs"));
run("node", ["verify.mjs", root], consumer);
console.log(
    "Packed consumer: types, CSR build, PHP queries and Vue SSR passed.",
);
