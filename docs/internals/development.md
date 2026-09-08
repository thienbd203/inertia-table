# Development

Use PHP 8.3 or newer and a Node version supported by `package.json`. Install
both dependency sets before running the checks:

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
