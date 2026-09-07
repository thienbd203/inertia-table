# Table definitions

A dedicated table class is the source of truth for query capabilities and
server-managed workflows. Use an anonymous table for small read-only screens.

## Dedicated classes

Generate a class when the table needs actions, exports, Saved Views, custom
selection rules, or reusable query behavior:

```bash
php artisan make:inertia-table Admin/TopicsTable --model=Content/Topic
```

The generated class exposes these primary extension points:

```php
final class TopicsTable extends Table
{
    public function query(): Builder { /* ... */ }
    public function columns(): array { /* ... */ }
    public function filters(): array { return []; }
    public function actions(): array { return []; }
    public function exports(): array { return []; }
    public function views(): ?Views { return null; }
}
```

Use `Table::make()` when returning the table to Inertia. Runtime fluent methods
can adjust one instance without changing the class default:

```php
TopicsTable::make()
    ->reloadProps(['archivedCount']);
```

The protected `$name` property controls its URL namespace. It defaults to the
snake-cased table class name without the `Table` suffix.

## Anonymous tables

`Table::build()` accepts a model class or an existing Eloquent builder. The
builder is cloned before every resolution, so resolving the table cannot mutate
the controller's original query.

```php
use App\Models\Topic;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Table;

return inertia('Topics/Index', [
    'topics' => Table::build(
        resource: Topic::query()->where('archived', false),
        columns: [
            TextColumn::make('name')->searchable()->sortable(),
        ],
        filters: [
            BooleanFilter::make('is_featured', 'Featured'),
        ],
        name: 'topics',
        defaultSort: 'name',
        perPageOptions: [25, 50, 100],
        defaultPerPage: 25,
        transformModelUsing: fn (Topic $topic) => [
            ...$topic->toArray(),
            'display_name' => str($topic->name)->headline()->toString(),
        ],
    ),
]);
```

Anonymous tables also accept `search`, `pagination`, `paginationType`,
`debounceTime`, `withQueryBuilder`, `emptyState`, `stickyHeader`, and
`stickyBackdropFilter`. Set `pagination: false` to return every normalized row
and remove pagination controls.

Anonymous tables intentionally cannot declare actions, exports, or Saved Views.
Move to a dedicated class when the server must reconstruct a definition from a
signed endpoint or persisted state.

## Transforming row data

Override the model transformation when an Inertia row needs a stable application
shape:

```php
protected function transform(Model $model): array
{
    return [
        'id' => $model->getKey(),
        'name' => $model->name,
        'status_label' => $model->status->label(),
    ];
}
```

Laravel resources include the Eloquent primary key as table metadata, including
UUIDs and keys not named `id`.

## Extending the query builder

Use the same hook for visible results, selections, summaries, and exports:

```php
use Spatie\QueryBuilder\QueryBuilder;

protected function withQueryBuilder(QueryBuilder $query): QueryBuilder
{
    $query->where('topics.tenant_id', tenant()->id);

    return $query;
}
```

Keep tenant and authorization boundaries in server-side queries. They must not
depend on values supplied only by the browser.

## Next steps

- [Columns](/guide/columns)
- [Search and filters](/guide/search-and-filters)
- [Actions](/features/actions)
- [Exports](/features/exports)
- [Saved Views](/features/saved-views)
