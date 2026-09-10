import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { createServer } from "node:http";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");
const { consumer } = JSON.parse(
    readFileSync(join(root, "build/consumer/latest.json"), "utf8"),
);
const { render } = await import(
    pathToFileURL(join(consumer, "dist-ssr/ssr.js"))
);
const manifest = JSON.parse(
    readFileSync(join(consumer, "dist/.vite/manifest.json"), "utf8"),
);
const entry = manifest["main.ts"];
const assets = new Set(
    Object.values(manifest).flatMap((item) => [item.file, ...(item.css ?? [])]),
);
const delayArgument = process.argv.find((value) =>
    value.startsWith("--lazy-delay="),
);
const lazyDelay = Number(delayArgument?.split("=")[1] ?? 0);
if (!Number.isInteger(lazyDelay) || lazyDelay < 0 || lazyDelay > 10000) {
    throw new Error(
        "--lazy-delay must be an integer from 0 to 10000 milliseconds.",
    );
}
let failNextLazy = process.argv.includes("--lazy-fail-once");
const reverseLazy = process.argv.includes("--lazy-reverse");
let lazyRequestNumber = 0;
let requestNumber = 0;

const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, "http://localhost");
        const asset = url.pathname.slice(1);
        if (assets.has(asset)) {
            response.setHeader(
                "Content-Type",
                asset.endsWith(".css") ? "text/css" : "text/javascript",
            );
            response.end(readFileSync(join(consumer, "dist", asset)));
            return;
        }
        if (request.method !== "GET" || url.pathname !== "/topics") {
            response.writeHead(404).end("Not found");
            return;
        }
        const sequence = ++requestNumber;
        const started = Date.now();
        response.on("close", () => {
            console.log(
                JSON.stringify({
                    sequence,
                    phase: response.writableFinished
                        ? "finished"
                        : "disconnected",
                    elapsedMs: Date.now() - started,
                }),
            );
        });
        const isLazy = Boolean(
            request.headers["x-musing-inertia-table-lazy-filters"],
        );
        console.log(
            JSON.stringify({
                sequence,
                phase: "received",
                url: request.url,
                partial: request.headers["x-inertia-partial-data"],
                lazy: isLazy,
            }),
        );
        const delay = isLazy
            ? reverseLazy
                ? ++lazyRequestNumber === 1
                    ? 10000
                    : 500
                : lazyDelay
            : 0;
        if (delay) {
            await new Promise((resolve) => setTimeout(resolve, delay));
        }
        if (isLazy && failNextLazy) {
            failNextLazy = false;
            response
                .writeHead(503, { "Content-Type": "text/plain" })
                .end(
                    "Intentional consumer lazy-options failure. Close the Inertia error dialog and retry.",
                );
            return;
        }
        const page = JSON.parse(
            execFileSync("php", [join(root, "tests/Consumer/page.php")], {
                input: JSON.stringify({
                    url: request.url,
                    headers: request.headers,
                }),
                encoding: "utf8",
            }),
        );
        response.setHeader("Cache-Control", "no-store");
        response.setHeader("Vary", "X-Inertia");
        if (request.headers["x-inertia"]) {
            response.setHeader("Content-Type", "application/json");
            response.setHeader("X-Inertia", "true");
            response.end(JSON.stringify(page));
            return;
        }
        const output = url.searchParams.has("csr")
            ? {
                  head: [],
                  body: `<script data-page="app" type="application/json">${JSON.stringify(page).replaceAll("<", "\\u003c")}</script><div id="app"></div>`,
              }
            : await render(page);
        response.setHeader("Content-Type", "text/html; charset=utf-8");
        response.end(
            `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${output.head.join("")}${entry.css.map((css) => `<link rel="stylesheet" href="/${css}">`).join("")}</head><body>${output.body}<script type="module" src="/${entry.file}"></script></body></html>`,
        );
    } catch (error) {
        console.error(error);
        response.writeHead(500).end("Consumer fixture failed; see terminal.");
    }
});
server.listen(0, "127.0.0.1", () => {
    console.log(`SSR: http://127.0.0.1:${server.address().port}/topics`);
    console.log(`CSR: http://127.0.0.1:${server.address().port}/topics?csr=1`);
    console.log(
        `Lazy header requests: delay=${lazyDelay}ms, reverse=${reverseLazy}, failOnce=${failNextLazy}. Restart to reset failure and request log.`,
    );
});
