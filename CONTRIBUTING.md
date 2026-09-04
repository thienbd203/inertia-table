# Contributing

## Setup

```bash
composer install
npm install
```

Run the complete local checks before opening a pull request:

```bash
composer test
composer analyse
vendor/bin/pint --test

npm run format:check
npm run types:check
npm test
npm run build
```

PHP query behavior must remain portable across SQLite, MySQL and PostgreSQL.
When a change touches URLs, selection, actions, views or exports, include a test
for multiple named tables and for malformed or tampered input where applicable.

## Bug reports

Include:

- Composer and npm package versions;
- PHP, Laravel, Inertia, Vue, Node and database versions;
- a minimal table definition;
- the normalized table query string;
- expected and actual behavior;
- a minimal reproduction repository when the issue depends on a consumer build.

## Pull requests

- Keep PHP callbacks and query builders on the server.
- Preserve unrelated URL state and per-table isolation.
- Keep all-matching selection semantics unchanged unless the change explicitly
  updates that public contract.
- Update tests and public documentation in the same change.
- Do not commit generated `dist`, dependency directories or local roadmap files.
