# Columns

Columns declare the data a table may expose and the presentation behavior used
by the default Vue renderer.

## Built-in column types

| Class | Typical value |
| --- | --- |
| `TextColumn` | Text and links |
| `NumberColumn` / `NumericColumn` | Numeric values and formatting |
| `BadgeColumn` | Status labels, variants, and icons |
| `BooleanColumn` | Boolean state |
| `DateColumn` | Calendar dates |
| `DateTimeColumn` | Date and time values |
| `ImageColumn` | One or more image URLs |
| `ActionColumn` | Row action controls |

All content columns share presentation methods for sorting, searching,
visibility, alignment, wrapping, truncation, tooltips, and CSS classes.

```php
TextColumn::make('status')
    ->sortable()
    ->mapAs(['pending' => 'Pending', 'approved' => 'Approved'])
    ->sortUsingMap();

TextColumn::make('priority')
    ->sortable()
    ->sortUsingPriority(['urgent', 'normal', 'low']);

TextColumn::make('description')
    ->wrap()
    ->truncate(2)
    ->cellClass('max-w-md');

DateTimeColumn::make('published_at', 'Published')
    ->format('d/m/Y H:i')
    ->centerAligned();
```

## Sorting and search

`sortable()` and `searchable()` add only the declared attribute to the server
allowlist. Use a callback for an expression or domain-specific order:

```php
use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\SortDirection;

TextColumn::make('score')->sortable()->sortUsing(
    fn (Builder $query, SortDirection $direction) =>
        $query->orderBy('score', $direction->value),
);
```

For nested attributes, read [relationships](/guide/relationships).

## Badges and images

```php
use Musing\InertiaTable\Columns\BadgeColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Image;
use Musing\InertiaTable\Variant;

BadgeColumn::make('status')
    ->mapAs(['active' => 'Active', 'blocked' => 'Blocked'])
    ->variant([
        'active' => Variant::Success,
        'blocked' => Variant::Danger,
    ])
    ->icon(['active' => 'CheckCircle', 'blocked' => 'XCircle']);

TextColumn::make('name')->image(
    'avatar_url',
    fn (Image $image) => $image->rounded()->large()->alt('User avatar'),
);
```

Icon names are resolved by the host application. See
[rendering and slots](/customization/rendering-and-slots).

## Row and cell navigation

### Callback arguments

| Method | Arguments, in order | Result |
| --- | --- | --- |
| `mapAs($callback)` | Raw attribute value, row `Model` | Display value |
| `exportAs($callback)` | Value **after `mapAs`**, row `Model` | Export value |
| `url($callback)` | Row `Model`, fresh `Url` | Return a URL string or `Url`; `null` omits the link |
| `image($callback)` | Row `Model`, fresh `Image` | Return an `Image`, or mutate the supplied instance |
| `image('attribute', $configure)` | `Image` with its URL set, row `Model` | Return an `Image`, or mutate the supplied instance |
| `sortUsing($callback)` | Eloquent `Builder`, `SortDirection` | Mutate the builder; the return value is ignored |
| `exportFormat($callback)` | This `Column` | Format string or `null` |

The two image callback forms have different argument orders. With a callback
as the first argument, a second configure callback is not used. URL callbacks
must return their result; mutating the supplied `Url` without returning it does
not create a link. Image callbacks can mutate without returning.

Fluent column methods return the concrete column type. Callback model arguments
are documented as Eloquent `Model`; table-specific model inference is not
provided. Type your model parameter explicitly in application code when useful.

Cell URLs may be strings or `Url` objects. `Url` carries Inertia navigation
options to the renderer.

```php
use Musing\InertiaTable\Url;

TextColumn::make('name')->url(
    fn (Topic $topic, Url $url) => $url
        ->route('topics.edit', $topic)
        ->openInNewTab(),
);
```

Rows are not clickable by default. Handle the optional event when the screen
needs row-level navigation:

```vue
<DataTable
    :resource="topics"
    @row-click="(item, column) => inspect(item, column)"
/>
```

## Sizing and ordering

```php
TextColumn::make('name')
    ->width(280)
    ->minWidth(180)
    ->maxWidth(480)
    ->resizable()
    ->reorderable();

ActionColumn::new()->width(64);
```

Widths are pixels. The server clamps declared defaults and URL or Saved View
state to the declared minimum and maximum. Resize handles support pointer,
touch, and keyboard input. Layout changes render locally and are debounced into
the table's URL namespace.

Disable interactions globally with `columns.resizable` or
`columns.reorderable`, or on one table with `columnResizing(false)` and
`columnReordering(false)`.

## Sticky layout

```php
final class TopicsTable extends Table
{
    protected ?bool $stickyHeader = true;
    protected ?bool $stickyFooter = true;
}
```

`stickable()` lets a user pin or unpin a column. `sticky()` permanently pins it:

```php
return [
    NumberColumn::make('id')->sticky(),
    TextColumn::make('name')->stickable(),
    ActionColumn::new()->sticky(),
];
```

Pinned offsets are measured from visible column widths and adapt to RTL layouts.
Pin state is stored in URL state and Saved Views without changing the selection
identity.

## Action column

```php
ActionColumn::new()->asDropdown();
```

The dropdown groups row actions behind one accessible trigger. Dynamic
`action(<key>)` slots work in inline and dropdown layouts.

## Empty state

Return a richer empty state only when the unfiltered base query is empty:

```php
use Musing\InertiaTable\EmptyState;
use Musing\InertiaTable\Url;
use Musing\InertiaTable\Variant;

public function emptyState(): ?EmptyState
{
    return EmptyState::make('No topics yet', 'Create the first topic.')
        ->dataAttributes(['kind' => 'topics'])
        ->action(
            label: 'Create topic',
            url: fn (Url $url) => $url->route('topics.create'),
            variant: Variant::Info,
            icon: 'Plus',
        );
}
```

A search or filter with no matches keeps the normal “No results found” UI.

## Safe row attributes

```php
public function dataAttributesForModel(Model $model, array $data): array
{
    return [
        'record-id' => $model->getKey(),
        'status' => $data['status_label'],
    ];
}
```

Only scalar or null values are accepted. Package-owned `data-selected` and
`data-row-clickable` attributes cannot be replaced.

For complete-result footer values, continue with [summaries](/features/summaries).
