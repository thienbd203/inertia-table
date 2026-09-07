# Actions

Actions are declared on the server and may run for one row, an explicit bulk
selection, or every selectable result matching the current search and filters.

## Row actions

```php
use Musing\InertiaTable\Actions\Action;

Action::make('delete', 'Delete')
    ->row()
    ->destructive()
    ->icon('Trash2')
    ->hideLabel()
    ->tooltip('Delete topic')
    ->authorized(
        fn (Topic $topic) => auth()->user()->can('delete', $topic),
    )
    ->handle(fn (Topic $topic) => $topic->delete())
    ->confirm(
        'Delete topic?',
        'This cannot be undone.',
        'Delete',
        'Cancel',
    );
```

`authorized()` is evaluated per model. Visibility, disabled state, and labels
may also vary per row.

## Bulk actions

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Musing\InertiaTable\Selection;

Action::make('archive', 'Archive')
    ->bulk()
    ->authorize(
        fn (Request $request) =>
            $request->user()->can('update', Topic::class),
    )
    ->before(fn (Selection $selection) => Log::info('Archiving topics', [
        'count' => $selection->count(),
    ]))
    ->handleSelection(fn (Selection $selection) => $selection
        ->query()
        ->update(['archived_at' => now()]))
    ->after(fn () => session()->flash('success', 'Topics archived'));
```

`handle()` invokes the callback for each eligible model in chunks, preserving
model events. `handleSelection()` invokes one callback with a typed `Selection`
for set-based work. A set-based callback owns any extra per-model constraints
that cannot be represented by `selectableQuery()`.

`before()` and `after()` run once around the request. An `after()` callback may
return a response or URL.

## Request and row authorization

- `authorize()` checks the request and action as a whole.
- `authorized()` checks a specific model.
- hidden, disabled, unauthorized, and unselectable rows are skipped by managed
  per-model bulk handlers.
- authorization and availability are checked again at the signed action
  endpoint.

Managed endpoints pass through Laravel `Response` and `Responsable` values.
Successful handlers without a response redirect back. Unexpected exceptions
remain visible to Laravel's exception handler.

## Selectability

Declare bulk eligibility at query and row level:

```php
public function selectableQuery(Builder $query): Builder
{
    return $query->whereNull('locked_at');
}

public function isSelectable(Model $model): bool
{
    return $model->locked_at === null;
}
```

Keep both rules equivalent. The query produces an exact `selectableTotal`; the
row check disables individual checkboxes. An unselectable row may still expose
row actions.

## All-matching selection

The header checkbox selects every eligible result across pages immediately.
Rows unchecked afterward are stored in `selection.except`. The browser sends a
descriptor instead of loading every ID:

```ts
{
    ids: [],
    selection: {
        all: true,
        keys: [],
        except: [42],
        table: "topics",
        state: {
            search: "laravel",
            filters: {
                status: {
                    enabled: true,
                    clause: "equals",
                    value: "published",
                },
            },
        },
    },
}
```

Managed handlers resolve this through `Selection`. Application-owned endpoints
must resolve the descriptor safely and must not apply raw client attributes to
SQL.

## Confirmation copy

Confirmation fields support `:count` and scalar row attributes such as `:name`:

```php
->confirm(
    [
        'Delete :count topic?',
        'Delete :count topics?',
        'Delete all :count matching topics?',
    ],
    [
        'This topic will be deleted.',
        ':count topics will be deleted.',
        'All :count matching topics will be deleted.',
    ],
    'Delete :count',
);
```

The all-matching variant falls back to the plural variant when omitted.

## Application routes

Use `endpoint()` when an existing route owns the workflow:

```php
Action::make('edit')
    ->row()
    ->endpoint('get', fn (Topic $topic) => route('topics.edit', $topic));
```

Omit both `handle()` and `endpoint()` for a frontend-owned action. `<DataTable>`
emits `custom-action` with `(action, keys, onFinish, selection)`. Call
`onFinish()` after custom work completes.

## Queued actions

Large managed bulk actions can be dispatched to Laravel's queue. Continue with
[queues](/features/queues).
