# Exports

Export definitions reuse the table's declared query, columns, selection rules,
and authorization. Native CSV requires no additional package.

## Callback parameters

Export callbacks use Laravel's container, with named arguments rather than a
fixed positional order. Declare only the parameters you need, using these names:

| Methods | Supplied names | Result |
| --- | --- | --- |
| `label`, `filename`, `authorize` | `$request`, `$table` | String, string, bool respectively |
| `modifyQueryUsing` | `$query`, `$request`, `$table`, `$state`, `$selection` | Eloquent builder, or nothing after mutating `$query` |

`$state` is the normalized state array, and `$selection` is an array or null.
For example, this callback mutates the existing query without returning it:

```php
use Illuminate\Database\Eloquent\Builder;

Export::make('csv')->modifyQueryUsing(function (Builder $query): void {
    $query->whereNotNull('published_at');
});
```

The container may inject additional services. Preserve the documented names to
receive the provided instances rather than asking it to construct replacements.
See [queue callback parameters](/features/queues#container-callback-parameters)
for delivery URLs, notification hooks and dispatch-time configuration.

## Declare export choices

```php
use Illuminate\Http\Request;
use Musing\InertiaTable\Exports\Export;

public function exports(): array
{
    return [
        Export::make('all', 'All topics'),
        Export::make('filtered', 'Filtered topics')->filtered(),
        Export::make('selected', 'Selected topics')->selected(),
        Export::make('excel', 'Excel', type: 'xlsx')
            ->filtered()
            ->authorize(
                fn (Request $request) => $request->user()->can('export'),
            ),
    ];
}
```

| Scope | Rows |
| --- | --- |
| all | Base `Table::query()` without browser state |
| filtered | Current normalized search, filters, and sort |
| selected | Explicit or all-matching `Selection`, including exclusions |

A selected export enables checkboxes even when the table has no bulk actions.
Starting an export does not clear selection.

## Exported columns

Columns are exportable by default except `ActionColumn`.

```php
TextColumn::make('reference')
    ->exportAs(fn (string $value, Topic $topic) => "#{$value}");

TextColumn::make('internal_notes')->dontExport();

NumberColumn::make('amount')
    ->exportFormat('#,##0.00')
    ->exportMeta(['style' => ['font' => ['bold' => true]]]);
```

`visibleColumnsOnly()` follows normalized visibility while keeping declared
order. `visibleColumnLayout()` follows visibility and the normalized user order.

## Native CSV

CSV reads Eloquent models in eager-loaded chunks, streams rows immediately,
uses UTF-8 with a BOM by default, and protects spreadsheet formula prefixes.

```php
use Illuminate\Database\Eloquent\Builder;

Export::make('archive')
    ->filtered()
    ->chunkSize(2_000)
    ->modifyQueryUsing(fn (Builder $query) => $query->select([
        'topics.id',
        'topics.name',
    ]))
    ->meta(['delimiter' => ';', 'bom' => false]);
```

Query modifiers run after scope, filters, and sort are resolved. They may mutate
the builder or return a replacement Eloquent builder.

## Summary row

```php
Export::make('report')
    ->filtered()
    ->withSummaries();
```

Native synchronous and queued CSV exports append raw summary values aligned with
the exported columns.

## XLSX and PDF

Install the optional adapter:

```bash
composer require maatwebsite/excel
```

Requesting XLSX or PDF without the adapter returns a validation error. Custom
formats implement `Musing\InertiaTable\Contracts\Exporter` and are registered
under `inertia-table.exporters.<type>`.

## Queued exports

```php
use Illuminate\Support\Facades\Storage;
use Musing\InertiaTable\Exports\QueuedExportSnapshot;
use Throwable;

Export::make('archive', 'Export archive')
    ->filtered()
    ->queue(
        connection: 'redis',
        queue: 'exports',
        delay: 5,
        disk: 's3',
        expiresAfter: 86_400,
    )
    ->redirectAfterDispatch('/exports')
    ->deliveryUrlUsing(
        fn (QueuedExportSnapshot $snapshot) =>
            Storage::disk($snapshot->disk)
                ->temporaryUrl($snapshot->path, now()->addHour()),
    )
    ->onReady(
        fn (QueuedExportSnapshot $snapshot, ?string $url) => null,
    )
    ->onFailure(
        fn (QueuedExportSnapshot $snapshot, Throwable $exception) => null,
    );
```

The default context captures and restores the authenticated actor before
authorization. Multi-tenant applications can add scalar identifiers through
`scopeAttributes()` and restore them with an `ExportContext` passed to
`context()`.

Queued exports capture the dispatch locale. Completed files expire and partial
files are deleted after failure. See [queues](/features/queues) for cache, worker,
idempotency, and deployment requirements.

## Vue lifecycle

The renderer sends signed POST requests and uses Laravel's CSRF meta tag or
`XSRF-TOKEN` cookie. It emits `export-success`, `export-queued`, and
`export-error`.

Queued state is `dispatched`, `processing`, `ready`, `failed`, or `expired`.
Without a redirect, the renderer polls the signed actor-scoped status endpoint.
A ready status with a URL shows a download action.

For an application-owned UI, compose [the headless API](/customization/headless-api).
