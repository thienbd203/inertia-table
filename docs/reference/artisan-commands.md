# Artisan commands

## `make:inertia-table`

Generate a dedicated table class under `app/Tables`:

```bash
php artisan make:inertia-table TopicsTable
php artisan make:inertia-table Admin/TopicsTable --model=Content/Topic
```

| Argument or option | Meaning |
| --- | --- |
| `name` | Table class name, optionally including subdirectories |
| `--model=` | Eloquent model used by the generated class |
| `--force` | Replace an existing generated file |

When `--model` is omitted, the command removes the `Table` suffix and infers a
singular model name. `Admin/TopicsTable`, for example, uses `App\Models\Topic`.
The generator refuses to replace an existing class unless `--force` is present.

## Vendor publishing

```bash
php artisan vendor:publish --tag=inertia-table-config
php artisan vendor:publish --tag=inertia-table-translations
php artisan vendor:publish --tag=inertia-table-migrations
```

| Tag | Use it when |
| --- | --- |
| `inertia-table-config` | Global defaults must differ from the package defaults |
| `inertia-table-translations` | Laravel-generated labels need application wording |
| `inertia-table-migrations` | The application enables Saved Views |

Run `php artisan migrate` after publishing the Saved View migration.
