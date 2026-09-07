# Summaries

Summary cells describe the complete normalized search and filter result, not
only rows on the visible page. Built-in summaries for a table are resolved in a
single SQL query.

## Built-in aggregates

```php
use Musing\InertiaTable\Summaries\SummaryAggregate;

NumberColumn::make('id', 'Orders')
    ->summary(SummaryAggregate::Count);

NumberColumn::make('total', 'Total')
    ->summary('sum')
    ->summaryFormat('#,##0.00');

NumberColumn::make('average', 'Average')
    ->summary('avg', 'total');
```

Available aggregates are:

- `count`
- `count_distinct`
- `sum`
- `avg`
- `min`
- `max`

`count` counts filtered rows. Other aggregates use the column attribute unless
a base column name is provided as the second argument.

## Custom summaries

Relationship paths and SQL expressions stay explicit through a server-only
callback:

```php
use Illuminate\Database\Eloquent\Builder;

NumberColumn::make('paid_total', 'Paid total')
    ->summaryUsing(
        fn (Builder $query) => (clone $query)
            ->where('status', 'paid')
            ->sum('total'),
    );
```

Clone the query before adding conditions when the callback performs a terminal
aggregate.

## Renderer behavior

The default footer stays aligned with visible, resized, reordered, and pinned
columns. During navigation it hides stale values and exposes a loading state.

Override the whole footer with `summaryFooter`, or one cell with
`summary(<attribute>)`. A cell slot receives `column`, `definition`, `value`, and
`formatted`.

```vue
<DataTable :resource="orders">
    <template #summary(total)="{ formatted }">
        <strong>{{ formatted }}</strong>
    </template>
</DataTable>
```

## Sticky footer

```php
final class OrdersTable extends Table
{
    protected ?bool $stickyFooter = true;
}
```

Sticky headers and footers share the bounded table viewport. Adjust
`--tb-sticky-max-height` in application CSS when the default does not fit the
screen.

## Exporting summaries

Call `withSummaries()` on an export to append a final row aligned with declared
export columns. Summary rows are supported by synchronous and queued native CSV
exports. See [exports](/features/exports).
