# Development

Use PHP 8.3 or newer and a Node version supported by `package.json`. Install
both dependency sets before running the checks:

```bash
composer install
npm install
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
environment across test files.

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

## URL contract fixture

PHP and TypeScript share URL-state fixtures. Regenerate the fixture from the PHP
suite, then verify it in Vitest:

```bash
INERTIA_TABLE_URL_CONTRACT_OUTPUT=build/contracts/urls.json composer test -- tests/UrlContractTest.php
INERTIA_TABLE_URL_CONTRACT_OUTPUT=build/contracts/urls.json npm test -- tests-js/contractUrl.test.ts
```

Keep the generated contract out of source control.
