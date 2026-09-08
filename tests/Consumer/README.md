# Packed consumer smoke

From the repository root, after `composer install` and `npm ci`:

```sh
npm run test:consumer
node tests/Consumer/serve.mjs
```

The first command builds and packs the Vue package, installs its tarball in a
fresh temporary project with strict peer resolution, typechecks the page, builds
Tailwind and CSR/SSR entries, and checks real Laravel search/sort/lazy-filter
responses plus Vue server HTML. Use `-- --offline` only with a populated npm cache.
Peer versions match the root's installed versions; this is not a compatibility
matrix. The fixture currently targets Inertia 3's JSON script page payload.

The second command prints local SSR and CSR URLs on an available port. It serves
the latest consumer recorded in `build/consumer/latest.json`. Re-run the first
command after source changes and restart the server. Stop it with Ctrl-C.

Browser smoke (manual; the command above does not automate a browser):

1. Open SSR URL. Three rows should appear with no hydration warnings.
2. Search `Beta`: only Beta remains. Clear search.
3. Name → Desc: Gamma, Beta, Alpha. URL contains sort `-name`.
4. Filters → Status → Select options: Published and Draft load on demand.
5. Select Draft: only Beta remains; the selected item keeps focus.
6. Reload the resulting URL: Beta and the active filter remain.
7. Open CSR URL and repeat search/sort; inspect browser console for errors.

PHP uses this checkout's Composer autoloader and explicitly registered providers
inside Testbench, with a fresh SQLite memory database per request. It does not
verify a Composer release archive or automatic package discovery. Vue SSR runs
through the real Inertia adapter in Node, without a Laravel SSR daemon or router
mocks. No playground database is used. Tarballs and the latest-run pointer are
ignored under `build/consumer`; installed consumers remain in the OS temporary
directory for inspection.
