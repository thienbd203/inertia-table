# Configuration reference

Publish `config/inertia-table.php` only when the application needs different
global defaults.

```bash
php artisan vendor:publish --tag=inertia-table-config
```

## Table defaults

| Key | Default | Meaning |
| --- | --- | --- |
| `per_page` | `25` | Initial page size |
| `per_page_options` | `[10, 25, 50, 100]` | Allowed page sizes |
| `pagination_type` | `'full'` | `full`, `simple`, or `cursor` |
| `debounce` | `300` | Search/filter/layout debounce in milliseconds |

## Sticky layout

| Key | Default | Meaning |
| --- | --- | --- |
| `sticky.footer` | `false` | Default sticky summary footer behavior |
| `sticky.backdrop_filter` | `true` | Blur horizontally sticky body/footer cells |

## Column interactions

| Key | Default | Meaning |
| --- | --- | --- |
| `columns.resizable` | `true` | Allow columns marked `resizable()` to resize |
| `columns.reorderable` | `true` | Allow columns marked `reorderable()` to move |

## Managed actions

| Key | Default | Meaning |
| --- | --- | --- |
| `action_path` | `_inertia-table/actions` | Signed action route prefix |
| `actions.queue.connection` | `null` | Laravel default connection when null |
| `actions.queue.queue` | `null` | Connection default queue when null |
| `actions.queue.delay` | `0` | Dispatch delay in seconds |
| `actions.queue.expires_after` | `86400` | Operation expiry in seconds |
| `actions.queue.status_retention` | `86400` | Terminal status retention in seconds |
| `actions.queue.after_commit` | `true` | Dispatch after active DB transaction commits |

## Exports

| Key | Default | Meaning |
| --- | --- | --- |
| `export_path` | `_inertia-table/exports` | Signed export route prefix |
| `exporters.csv` | `NativeCsvExporter::class` | CSV adapter |
| `exporters.xlsx` | `LaravelExcelExporter::class` | Optional XLSX adapter |
| `exporters.pdf` | `LaravelExcelExporter::class` | Optional PDF adapter |
| `exports.chunk_size` | `1000` | Default Eloquent export chunk size |

## Relationship sorting

| Key | Default |
| --- | --- |
| `relationship_sorter` | `PowerJoinsRelationshipSorter::class` |

The default adapter requires the optional Eloquent Power Joins package only when
an automatic to-one relationship sort is executed.

## Queued exports

| Key | Default | Meaning |
| --- | --- | --- |
| `queue.connection` | `null` | Laravel default connection when null |
| `queue.queue` | `null` | Connection default queue when null |
| `queue.delay` | `0` | Dispatch delay in seconds |
| `queue.disk` | `'local'` | Filesystem disk for generated files |
| `queue.path` | `'table-exports'` | Directory on the selected disk |
| `queue.expires_after` | `604800` | File/status expiry in seconds |

## Saved Views

| Key | Default | Meaning |
| --- | --- | --- |
| `view_path` | `_inertia-table/views` | Signed Saved View route prefix |
| `views.table` | `'table_views'` | Persistence table name |

Changing route paths invalidates previously signed URLs. Changing the Saved View
table name requires the published migration and model configuration to agree.
