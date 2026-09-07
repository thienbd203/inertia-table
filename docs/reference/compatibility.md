# Compatibility

## Runtime requirements

| Layer | Supported versions |
| --- | --- |
| PHP | 8.3 and newer |
| Laravel | 12 and 13 |
| Inertia Laravel | 2 and 3 |
| Vue | 3.4 and newer |
| Tailwind CSS | 4.1 and newer |
| Reka UI | 2.10 and newer |
| `@lucide/vue` | 1.30 and newer |
| Spatie Laravel Query Builder | 7 |

The PHP and Vue packages are released together. Keep them on compatible package
versions because both sides consume the same table resource schema.

## Optional dependencies

| Package | Required for |
| --- | --- |
| `kirschbaum-development/eloquent-power-joins` 4.3+ | Automatic to-one relationship sorting |
| `maatwebsite/excel` | XLSX and PDF export adapters |

CSV export, direct-column sorting, filters, actions, Saved Views, and queues do
not require either optional package.

## Supported databases

The package test matrix covers SQLite, MySQL, and PostgreSQL. Application-owned
raw expressions and custom query callbacks remain responsible for database
portability.

## Versioning status

The package is actively developed before 1.0, so minor releases may include API
changes. The intended post-1.0 guarantees are described in
[API stability](/internals/api-stability).
