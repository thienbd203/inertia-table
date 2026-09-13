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
use App\Models\Category;
use Musing\InertiaTable\Filters\SetFilter;

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

This example assumes your host has a `Category` model with a `name` attribute
and that the table query exposes `category_id`. Put the definition in the
table's `filters()` method. Lazy loading defers the option list; it does not
provide server-side option search or option pagination.

For a runnable example without a related model, see the lazy Status filter in
the [consumer fixture](https://github.com/thienbd203/inertia-table/blob/master/tests/Consumer/TopicsTable.php).
The [consumer instructions](https://github.com/thienbd203/inertia-table/tree/master/tests/Consumer)
describe how to run it.

### Options stay empty or loading fails

Check that the table name matches the Inertia prop key and that the current
route still returns the same page component. Opening a lazy filter requests
that table prop; a response that omits it cannot supply the options. Inspect
the partial response and the host's Laravel logs if the request fails.

An empty successful option list is different from a failed request: verify the
model source and its scopes when no options are returned. Loading options does
not bypass application query scopes or create missing records.

### A range does not apply after entering its first value

A range requires both endpoints. The editor keeps an incomplete range as a
draft until it is complete. Changing the clause or removing the filter cancels
the pending draft update. Single-value selections apply without an Apply button.

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
