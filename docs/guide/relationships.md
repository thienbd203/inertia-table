# Relationship queries

Declared columns and filters may use nested Eloquent paths. Dot notation is
accepted only from server definitions; arbitrary client paths never become
query constraints.

```php
public function columns(): array
{
    return [
        TextColumn::make('author.name', 'Author')
            ->searchable()
            ->sortable(),
        TextColumn::make('author.company.name', 'Company')
            ->searchable(),
        NumberColumn::make('comments.score', 'Comment score')
            ->sortable(),
    ];
}

public function filters(): array
{
    return [
        TextFilter::make('author.company.name', 'Company'),
        NumericFilter::make('comments.score', 'Comment score'),
    ];
}
```

## Search and filters

Relationship search and filters use nested `whereHas` constraints. A has-many
match therefore does not duplicate base rows. Direct columns are qualified with
the model table to avoid ambiguous-column errors.

Nullable relationship filters include a missing relationship for
`is_not_set`.

## To-one sorting

Automatic to-one sorting uses the optional Eloquent Power Joins adapter with a
left join, preserving rows whose relationship is null:

```bash
composer require kirschbaum-development/eloquent-power-joins
```

The configured adapter is `inertia-table.relationship_sorter`. Implement the
`RelationshipSorter` contract and replace that config value when an application
needs another strategy.

## To-many sorting

To-many sorting stays duplicate-safe by ordering on a correlated `MIN` for
ascending order and `MAX` for descending order. Use `sortUsing()` when the
domain needs a different aggregate or order.

## Eager loading

Relationship paths used for display should be eager loaded in `query()`:

```php
public function query(): Builder
{
    return Topic::query()->with(['author.company']);
}
```

Search and sorting constraints do not replace eager loading for model
serialization.

## Query customization

```php
use Spatie\QueryBuilder\QueryBuilder;

protected function withQueryBuilder(QueryBuilder $query): QueryBuilder
{
    $query->where('topics.tenant_id', tenant()->id);

    return $query;
}
```

The hook applies to results, explicit and all-matching selections, summaries,
and every export scope. When a base query adds joins, the package selects and
deduplicates by the qualified model primary key. Custom selected projections
and raw sort callbacks still own their SQL portability.

## Choosing a strategy

| Need | Approach |
| --- | --- |
| Display related data | Eager load in `query()` |
| Search or filter related data | Declare a dotted column or filter path |
| Sort a belongs-to/has-one value | Install Power Joins or use `sortUsing()` |
| Sort a has-many value | Use the built-in aggregate or `sortUsing()` |
| Apply tenant/global constraints | Use the base query or `withQueryBuilder()` |
