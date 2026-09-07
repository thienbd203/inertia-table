# Search and filters

Search and filters are server allowlists. The browser submits normalized state;
the table decides which attributes, clauses, and option values are valid.

## Global search

Mark individual columns:

```php
TextColumn::make('name')->searchable();
TextColumn::make('email')->searchable();
```

Or override the resolved search list on the table:

```php
protected array|string|null $search = ['name', 'email'];

// Disable global search even when a column is marked searchable.
protected array|string|null $search = [];
```

Search and filter changes use Inertia partial reloads for the table prop and any
declared `reloadProps`.

## Built-in filters

| Filter | Common clauses |
| --- | --- |
| `TextFilter` | `contains`, `starts_with`, `equals`, `not_equals` |
| `NumericFilter` | comparisons, equality, and ranges |
| `SetFilter` | `in`, `not_in`, `equals`, `not_equals` |
| `BooleanFilter` | `is_true`, `is_false` |
| `DateFilter` | before, after, equality, and date ranges |

```php
use Musing\InertiaTable\Filters\DateFilter;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Filters\TextFilter;

return [
    TextFilter::make('name', 'Name')->clauses([
        'contains',
        'starts_with',
        'equals',
        'not_equals',
    ]),
    SetFilter::make('status', 'Status')->options([
        'published' => 'Published',
        'draft' => 'Draft',
    ]),
    DateFilter::make('created_at', 'Created at'),
];
```

`SetFilter` uses a multi-select for `in` and `not_in`. `DateFilter` uses a
single-date calendar and a two-month range calendar for range clauses.

## Lazy set options

Large option lists do not need to be serialized with the first table response.
Declare a model source and call `lazy()`:

```php
SetFilter::make('category_id', 'Category')
    ->pluckOptionsFromModel(Category::class, 'name')
    ->multiple()
    ->lazy();
```

The first time the filter opens, the renderer requests the options with an
Inertia partial reload. Later table visits retain the loaded options. Selecting
an option still updates the table immediately; there is no separate Apply step.

The model primary key is the default value column. Pass a third argument to use
another declared model attribute:

```php
->pluckOptionsFromModel(Category::class, 'name', 'slug')
```

## Custom query behavior

Keep an option allowlist even when the application owns the query logic:

```php
use Illuminate\Database\Eloquent\Builder;

SetFilter::make('status')->options([
    'empty' => 'Without quotes',
    'featured' => 'Featured',
])->applyUsing(function (
    Builder $query,
    string|array $value,
    string $clause,
): void {
    $values = (array) $value;

    if ($clause === 'equals' && $values[0] === 'empty') {
        $query->doesntHave('quotes');
    }
});
```

The callback receives only a value already normalized by the filter definition.

## Custom clause value shapes

Custom filters can tell the renderer whether a clause expects one value, a
range, or no value:

```php
$filter->clauses(['equals', 'matches_range', 'is_blank'])
    ->clauseValueKinds([
        'matches_range' => 'range',
        'is_blank' => 'none',
    ]);
```

The custom filter remains responsible for normalization and query application.
The metadata only selects the appropriate client control.

## Clause-less application controls

Use `withoutClause()` when an application-owned slot needs to store one value
without exposing clause selection:

```php
NumericFilter::make('source_id', 'Source')->withoutClause();
```

See [rendering and slots](/customization/rendering-and-slots) for an async custom
control.

## Deprecated alias

`SelectFilter` remains as a deprecated alias for `SetFilter`. New code should
use `SetFilter`.

Nested filter paths are covered in [relationships](/guide/relationships).
