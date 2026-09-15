# Development

Use PHP 8.3 or newer and a Node version supported by `package.json`. Install
both dependency sets before running the checks:

Run repository tests with Node 22.22.2 or 24.15.0, matching CI. Vitest 5 does
not support Node 20. Package build/consumer smoke still runs on Node 20.19.0
to cover the package's declared engine range; that job does not execute Vitest.

```bash
composer install
npm ci
```

## PHP checks

```bash
composer test
composer analyse
vendor/bin/pint --test
```

The Pest suite uses Orchestra Testbench and SQLite by default. CI also runs the
supported Laravel and Inertia combinations plus MySQL and PostgreSQL jobs.

## Vue checks

```bash
npm run format:check
npm run types:check
npm test
npm run build
```

Vue tests set `isolate: false`, so each worker reuses its `happy-dom`
environment across test files. The shared setup unmounts wrappers before
restoring timers, browser mocks and DOM state. Each test still owns its setup;
tests must not depend on another file having run first.

To check for state leaking between files:

```bash
npm test -- --maxWorkers=1 --sequence.shuffle.files --sequence.seed=17
npm test -- --maxWorkers=1 --sequence.shuffle.files --sequence.seed=83
```

## Documentation

Start the local documentation server with:

```bash
npm run docs:dev
```

Build the same static site used by CI and GitHub Pages with:

```bash
npm run docs:build
```

VitePress writes generated files below `docs/.vitepress/dist`; do not commit
that directory.

## Contract fixtures

The PHP resource test verifies the committed TypeScript resource fixture:

```bash
vendor/bin/pest tests/ContractResourceTest.php
```

When the resource changes intentionally, regenerate it locally with
`INERTIA_TABLE_UPDATE_CONTRACTS=1 vendor/bin/pest tests/ContractResourceTest.php`
and review the diff in `tests-js/contracts/resource.generated.ts`. CI verifies
freshness and does not regenerate this file.

For the URL bridge, TypeScript writes URLs and PHP verifies their normalized
state. Run these commands in order:

```bash
INERTIA_TABLE_URL_CONTRACT_OUTPUT=build/contracts/urls.json npm test -- tests-js/contractUrl.test.ts
INERTIA_TABLE_URL_CONTRACT_INPUT=build/contracts/urls.json vendor/bin/pest tests/ContractUrlTest.php
```

The PHP URL test skips when no input path is supplied. The generated URL
artifact under `build/` is temporary and must not be committed; the TypeScript
resource fixture is tracked in Git.

## Test ownership and reproduction

| Boundary | Check | What it proves |
| --- | --- | --- |
| PHP query/state/serialization | `vendor/bin/pest tests/TableTest.php tests/RelationshipQueryTest.php tests/SelectionTest.php` | Server allowlists, scoped queries, pagination and row metadata |
| Summary and exports | `vendor/bin/pest tests/SummaryTest.php tests/ExportTest.php` | Aggregate query isolation, extension hooks and exported results |
| PHP consumer callback types | `composer analyse:consumer` and `composer analyse:consumer:negative` | Valid consumer signatures compile and known invalid callbacks fail |
| Headless state/actions | `npm test -- tests-js/useTable.test.ts tests-js/useActions.test.ts` | State transitions and request callbacks with the unit router mock |
| Renderer | `npm test -- tests-js/DataTable.test.ts` | Component composition in happy-dom; not browser layout |
| PHP/Vue bridge | Resource and URL commands above | Resource freshness and frontend URLs accepted by PHP |
| Installed consumer | `npm run test:consumer` | npm tarball install, public types, CSR/SSR builds and real PHP responses |
| Browser | `node tests/Consumer/serve.mjs` after the consumer check | Manual focus, navigation and hydration checks |

The consumer uses root-installed peer versions, not every supported peer version.
Its PHP bootstrap uses the checkout's Composer installation. See the
[consumer README](https://github.com/thienbd203/inertia-table/tree/master/tests/Consumer)
and [browser catalog](https://github.com/thienbd203/inertia-table/blob/master/tests/Consumer/ui-catalog.md)
for exact scenarios and recorded limitations.

For a bug report include package/host versions, table definition, sanitized URL,
expected/actual result, and the smallest relevant request/response. Reproduce
against a fixture database. Preserve the failing shuffle seed from test output;
do not fix flaky tests by adding retries or shared state. Test query bugs on the
affected database driver. For restricted local environments, PHPStan's `--debug`
mode runs without parallel worker sockets.
