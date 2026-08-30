<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\ActionColumn;
use Musing\InertiaTable\Columns\BadgeColumn;
use Musing\InertiaTable\Columns\BooleanColumn;
use Musing\InertiaTable\Columns\DateColumn;
use Musing\InertiaTable\Columns\ImageColumn;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Filters\NumericFilter;
use Musing\InertiaTable\Filters\SelectFilter;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Filters\TextFilter;
use Musing\InertiaTable\SortDirection;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Url;
use Musing\InertiaTable\Variant;

class TopicRecord extends Model
{
    protected $table = 'topics';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }
}

class TopicsTable extends Table
{
    protected ?string $defaultSort = 'name';

    public function query(): Builder
    {
        return TopicRecord::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID')->sortable()->toggleable(false),
            TextColumn::make('name', 'Name')
                ->searchable()
                ->sortable()
                ->url(fn (TopicRecord $topic) => "/topics/{$topic->id}"),
            NumberColumn::make('score', 'Score')->sortable(),
            BooleanColumn::make('is_featured', 'Featured'),
            ActionColumn::new(),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('edit')
                ->row()
                ->endpoint('get', fn (TopicRecord $topic) => "/topics/{$topic->id}"),
            Action::make('delete')
                ->bulk()
                ->destructive()
                ->icon('Trash')
                ->hideLabel()
                ->tooltip('Delete selected topics')
                ->endpoint('delete', '/topics/bulk')
                ->confirm('Delete topics', 'This action cannot be undone.'),
        ];
    }

    protected function rowUrl(Model $model): ?string
    {
        return "/topics/{$model->getKey()}";
    }

    public function filters(): array
    {
        return [
            TextFilter::make('name', 'Name'),
            SelectFilter::make('status')
                ->options(['featured' => 'Featured', 'regular' => 'Regular'])
                ->applyUsing(fn (Builder $query, string $value) => $query->where(
                    'is_featured',
                    $value === 'featured',
                )),
            BooleanFilter::make('is_featured', 'Featured'),
        ];
    }
}

class ExplicitSearchTopicsTable extends TopicsTable
{
    protected ?string $name = 'topics';

    protected array|string|null $search = [];
}

class StickyTopicsTable extends TopicsTable
{
    protected ?string $name = 'topics';

    protected ?bool $stickyHeader = true;

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID')->sticky()->toggleable(false),
            TextColumn::make('name', 'Name')->searchable()->sortable()->stickable(),
            NumberColumn::make('score', 'Score')->sortable(),
            BooleanColumn::make('is_featured', 'Featured'),
            ActionColumn::new()->sticky(),
        ];
    }
}

class AdvancedFilterTopicsTable extends TopicsTable
{
    protected ?string $name = 'topics';

    public function filters(): array
    {
        return [
            TextFilter::make('name'),
            NumericFilter::make('score'),
            BooleanFilter::make('is_featured'),
        ];
    }
}

class CustomPerPageTopicsTable extends TopicsTable
{
    protected ?string $name = 'topics';

    protected ?int $perPage = 1;

    /** @var array<int, int> */
    protected ?array $perPageOptions = [1, 2];
}

class SelectableTopicsTable extends TopicsTable
{
    protected ?string $name = 'topics';

    public function selectableQuery(Builder $query): Builder
    {
        return $query->where('score', '>=', 20);
    }

    public function isSelectable(Model $model): bool
    {
        return $model->getAttribute('score') >= 20;
    }
}

class PaginatedSelectableTopicsTable extends SelectableTopicsTable
{
    protected ?int $perPage = 1;

    /** @var array<int, int> */
    protected ?array $perPageOptions = [1];
}

class ExternalTopicRecord extends Model
{
    protected $table = 'external_topics';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;
}

class ExternalTopicsTable extends Table
{
    public function query(): Builder
    {
        return ExternalTopicRecord::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name', 'Name')];
    }
}

beforeEach(function () {
    Schema::create('topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('score')->default(0);
        $table->boolean('is_featured')->default(false);
    });

    TopicRecord::query()->insert([
        ['name' => 'Alpha', 'score' => 10, 'is_featured' => false],
        ['name' => 'Beta', 'score' => 30, 'is_featured' => true],
        ['name' => 'Gamma', 'score' => 20, 'is_featured' => true],
    ]);
});

function tableRequest(array $state = []): Request
{
    return Request::create('/topics', 'GET', [
        'table' => ['topics' => $state],
    ]);
}

it('serializes a versioned table resource', function () {
    $resource = (new TopicsTable)->resolve(tableRequest())->toArray();

    expect($resource)
        ->schemaVersion->toBe(1)
        ->name->toBe('topics')
        ->columns->toHaveCount(5)
        ->filters->toHaveCount(3)
        ->actions->toHaveCount(1)
        ->capabilities->toBe([
            'searchable' => true,
            'selectable' => true,
            'paginated' => true,
            'hasSearch' => true,
            'hasFilters' => true,
            'hasActions' => true,
            'hasBulkActions' => true,
            'hasExports' => false,
            'hasToggleableColumns' => true,
            'hasStickableColumns' => false,
        ])
        ->search->toBe(['name'])
        ->options->toBe([
            'debounceTime' => 300,
            'perPage' => [10, 25, 50, 100],
            'reloadProps' => [],
            'stickyHeader' => false,
        ])
        ->state->sort->toBe('name')
        ->state->columns->toBe([
            'id' => true,
            'name' => true,
            'score' => true,
            'is_featured' => true,
            '__actions' => true,
        ])
        ->results->total->toBe(3)
        ->and($resource['columns'][0])
        ->toMatchArray([
            'attribute' => 'id',
            'header' => 'ID',
            'type' => 'numeric',
            'sortable' => true,
            'toggleable' => false,
            'visibleByDefault' => true,
            'alignment' => 'left',
            'meta' => [],
        ]);
});

it('serializes the eloquent primary key as stable row metadata', function () {
    Schema::create('external_topics', function (Blueprint $table) {
        $table->string('uuid')->primary();
        $table->string('name');
    });
    ExternalTopicRecord::query()->create([
        'uuid' => 'topic-alpha',
        'name' => 'Alpha',
    ]);

    $resource = (new ExternalTopicsTable)->resolve(Request::create('/external-topics'))->toArray();

    expect($resource['results']['data'][0]['_table']['key'])->toBe('topic-alpha');
});

it('normalizes sticky header and pinned column state through declarations', function () {
    $defaults = (new StickyTopicsTable)->resolve(tableRequest())->toArray();
    $requested = (new StickyTopicsTable)->resolve(tableRequest([
        'pinnedColumns' => [
            'left' => ['name', 'score', '__actions'],
            'right' => ['name', 'id', 'unknown'],
        ],
    ]))->toArray();

    expect($defaults['options']['stickyHeader'])->toBeTrue()
        ->and($defaults['capabilities']['hasStickableColumns'])->toBeTrue()
        ->and($defaults['state']['pinnedColumns'])->toBe([
            'left' => ['id'],
            'right' => ['__actions'],
        ])
        ->and($requested['state']['pinnedColumns'])->toBe([
            'left' => ['id', 'name'],
            'right' => ['__actions'],
        ]);
});

it('serializes row eligibility and the exact selectable result count', function () {
    $resource = (new SelectableTopicsTable)->resolve(tableRequest())->toArray();

    expect($resource['results'])
        ->total->toBe(3)
        ->selectableTotal->toBe(2)
        ->and(array_map(
            fn (array $row) => $row['_table']['selectable'],
            $resource['results']['data'],
        ))->toBe([false, true, true]);
});

it('computes selectable totals against the normalized search and filters', function () {
    $resource = (new SelectableTopicsTable)->resolve(tableRequest([
        'filters' => ['status' => [
            'enabled' => true,
            'clause' => 'equals',
            'value' => 'regular',
        ]],
    ]))->toArray();

    expect($resource['results'])
        ->total->toBe(1)
        ->selectableTotal->toBe(0);
});

it('counts selectable rows outside the current page', function () {
    $resource = (new PaginatedSelectableTopicsTable)->resolve(tableRequest())->toArray();

    expect($resource['results'])
        ->currentPage->toBe(1)
        ->to->toBe(1)
        ->total->toBe(3)
        ->selectableTotal->toBe(2)
        ->and($resource['results']['data'][0]['_table']['selectable'])->toBeFalse();
});

it('searches only searchable columns', function () {
    $resource = (new TopicsTable)->resolve(tableRequest(['search' => 'amm']))->toArray();

    expect($resource['results']['data'])
        ->toHaveCount(1)
        ->and($resource['results']['data'][0]['name'])->toBe('Gamma');
});

it('can explicitly disable global search on the table', function () {
    $resource = (new ExplicitSearchTopicsTable)->resolve(tableRequest(['search' => 'Gamma']))->toArray();

    expect($resource['search'])->toBe([])
        ->and($resource['capabilities']['hasSearch'])->toBeFalse()
        ->and($resource['results']['total'])->toBe(3);
});

it('keeps commas intact in the global search filter', function () {
    TopicRecord::query()->create([
        'name' => 'Alpha, Incorporated',
        'score' => 40,
    ]);

    $resource = (new TopicsTable)->resolve(tableRequest([
        'search' => 'Alpha, Inc',
    ]))->toArray();

    expect($resource['results']['data'])
        ->toHaveCount(1)
        ->and($resource['results']['data'][0]['name'])->toBe('Alpha, Incorporated');
});

it('delegates partial text filtering to spatie query builder', function () {
    $resource = (new TopicsTable)->resolve(tableRequest([
        'filters' => ['name' => [
            'enabled' => true,
            'clause' => 'contains',
            'value' => 'amm',
        ]],
    ]))->toArray();

    expect($resource['results']['data'])
        ->toHaveCount(1)
        ->and($resource['results']['data'][0]['name'])->toBe('Gamma')
        ->and($resource['state']['filters']['name'])->toBe([
            'enabled' => true,
            'clause' => 'contains',
            'value' => 'amm',
        ])
        ->and($resource['state']['filters']['status']['enabled'])->toBeFalse();
});

it('sorts only declared sortable columns', function () {
    $descending = (new TopicsTable)->resolve(tableRequest(['sort' => '-score']))->toArray();
    $unknown = (new TopicsTable)->resolve(tableRequest([
        'sort' => 'name; DROP TABLE topics',
    ]))->toArray();

    expect(array_column($descending['results']['data'], 'name'))
        ->toBe(['Beta', 'Gamma', 'Alpha'])
        ->and($unknown['state']['sort'])->toBe('name')
        ->and(Schema::hasTable('topics'))->toBeTrue();
});

it('normalizes and applies declared filters', function () {
    $featured = (new TopicsTable)->resolve(tableRequest([
        'filters' => ['status' => [
            'enabled' => true,
            'clause' => 'equals',
            'value' => 'featured',
        ]],
    ]))->toArray();
    $invalid = (new TopicsTable)->resolve(tableRequest([
        'filters' => ['status' => [
            'enabled' => true,
            'clause' => 'equals',
            'value' => 'missing',
        ]],
    ]))->toArray();

    expect(array_column($featured['results']['data'], 'name'))
        ->toBe(['Beta', 'Gamma'])
        ->and($featured['state']['filters']['status'])->toBe([
            'enabled' => true,
            'clause' => 'equals',
            'value' => 'featured',
        ])
        ->and($invalid['results']['total'])->toBe(3)
        ->and($invalid['state']['filters']['status']['enabled'])->toBeFalse();
});

it('applies text numeric and valueless boolean clauses', function () {
    $text = (new AdvancedFilterTopicsTable)->resolve(tableRequest([
        'filters' => ['name' => ['enabled' => true, 'clause' => 'not_contains', 'value' => 'ph']],
    ]))->toArray();
    $numeric = (new AdvancedFilterTopicsTable)->resolve(tableRequest([
        'filters' => ['score' => ['enabled' => true, 'clause' => 'greater_than', 'value' => 20]],
    ]))->toArray();
    $boolean = (new AdvancedFilterTopicsTable)->resolve(tableRequest([
        'filters' => ['is_featured' => ['enabled' => true, 'clause' => 'is_false', 'value' => null]],
    ]))->toArray();

    expect(array_column($text['results']['data'], 'name'))->toBe(['Beta', 'Gamma'])
        ->and(array_column($numeric['results']['data'], 'name'))->toBe(['Beta'])
        ->and(array_column($boolean['results']['data'], 'name'))->toBe(['Alpha']);
});

it('accepts only configured per-page values', function () {
    config()->set('inertia-table.per_page', 2);
    config()->set('inertia-table.per_page_options', [1, 2]);

    $allowed = (new TopicsTable)->resolve(tableRequest([
        'page' => 2,
        'perPage' => 1,
    ]))->toArray();
    $rejected = (new TopicsTable)->resolve(tableRequest(['perPage' => 999]))->toArray();

    expect($allowed['results'])
        ->currentPage->toBe(2)
        ->perPage->toBe(1)
        ->total->toBe(3)
        ->and($allowed['results']['data'][0]['name'])->toBe('Beta')
        ->and($rejected['results']['perPage'])->toBe(2);
});

it('lets a table override the global per-page default and options', function () {
    config()->set('inertia-table.per_page', 25);
    config()->set('inertia-table.per_page_options', [10, 25, 50, 100]);

    $default = (new CustomPerPageTopicsTable)->resolve(tableRequest())->toArray();
    $rejected = (new CustomPerPageTopicsTable)->resolve(tableRequest(['perPage' => 25]))->toArray();

    expect($default['options']['perPage'])->toBe([1, 2])
        ->and($default['results']['perPage'])->toBe(1)
        ->and($rejected['results']['perPage'])->toBe(1);
});

it('declares extra inertia props to reload', function () {
    $resource = (new TopicsTable)
        ->reloadProps('featuredCount')
        ->reloadProps(['trashedCount', 'featuredCount'])
        ->resolve(tableRequest())
        ->toArray();

    expect($resource['options']['reloadProps'])->toBe(['featuredCount', 'trashedCount']);
});

it('normalizes column visibility and never hides non-toggleable columns', function () {
    $resource = (new TopicsTable)->resolve(tableRequest([
        'columns' => ['id' => '0', 'score' => '0', 'unknown' => '0'],
    ]))->toArray();

    expect($resource['state']['columns'])
        ->id->toBeTrue()
        ->score->toBeFalse()
        ->not->toHaveKey('unknown');
});

it('serializes server-declared actions', function () {
    $resource = (new TopicsTable)->resolve(tableRequest())->toArray();

    expect($resource['actions'][0])->toBe([
        'key' => 'delete',
        'label' => 'Delete',
        'scope' => 'bulk',
        'authorized' => true,
        'disabled' => false,
        'hidden' => false,
        'variant' => 'destructive',
        'icon' => 'Trash',
        'labelHidden' => true,
        'tooltip' => 'Delete selected topics',
        'buttonClass' => null,
        'disabledTooltip' => null,
        'confirmation' => [
            'title' => 'Delete topics',
            'message' => 'This action cannot be undone.',
            'confirmLabel' => 'Yes',
            'cancelLabel' => 'Cancel',
        ],
        'endpoint' => ['method' => 'delete', 'url' => '/topics/bulk'],
        'meta' => [],
    ]);
});

it('resolves row action tooltips from the model', function () {
    $topic = new TopicRecord(['is_featured' => true]);

    $action = Action::make('toggle-featured')
        ->row()
        ->tooltip(fn (TopicRecord $topic) => $topic->is_featured ? 'Remove featured' : 'Mark featured');

    expect($action->resolve($topic)['tooltip'])->toBe('Remove featured');
});

it('supports set filter clauses, multiple values, and withoutClause', function () {
    $filter = SetFilter::make('status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
        ->multiple()
        ->compactDisplay('statuses');
    $withoutClause = SetFilter::make('status')
        ->options(['active' => 'Active'])
        ->withoutClause();

    expect($filter->toArray())
        ->clauses->toBe(['in', 'not_in', 'equals', 'not_equals'])
        ->multiple->toBeTrue()
        ->compactDisplayLabel->toBe('statuses')
        ->and($filter->normalize(['active', 'inactive'], 'in'))
        ->toBe(['active', 'inactive'])
        ->and($filter->normalize('active', 'not_in'))
        ->toBe(['active'])
        ->and($withoutClause->toArray())
        ->clauses->toBe(['equals'])
        ->showClause->toBeFalse();
});

it('resolves row action visibility, availability, and custom actions', function () {
    $topic = TopicRecord::query()->firstOrFail();

    $disabled = Action::make('archive', fn (TopicRecord $model) => "Archive {$model->name}")
        ->disabled(fn (TopicRecord $model) => $model->id === $topic->id)
        ->disabledTooltip('Already archived')
        ->buttonClass('text-blue-600')
        ->confirm()
        ->resolve($topic);
    $hidden = Action::make('restore')
        ->hidden(fn (TopicRecord $model) => $model->id === $topic->id)
        ->endpoint('post', '/topics/restore')
        ->resolve($topic);
    $custom = Action::make('inspect')->resolve($topic);

    expect($disabled)
        ->label->toBe("Archive {$topic->name}")
        ->disabled->toBeTrue()
        ->disabledTooltip->toBe('Already archived')
        ->buttonClass->toBe('text-blue-600')
        ->confirmation->toBe([
            'title' => 'Confirm action',
            'message' => 'Are you sure you want to perform this action?',
            'confirmLabel' => 'Yes',
            'cancelLabel' => 'Cancel',
        ])
        ->and($hidden['hidden'])->toBeTrue()
        ->and($custom['endpoint'])->toBeNull();
});

it('resolves row links, cell links, and row actions without frontend slots', function () {
    $resource = (new TopicsTable)->resolve(tableRequest())->toArray();
    $row = $resource['results']['data'][0];

    expect($row['_table'])
        ->url->toMatchArray([
            'url' => '/topics/1',
            'preserveScroll' => true,
            'preserveState' => true,
        ])
        ->columns->name->toMatchArray(['url' => '/topics/1'])
        ->actions->toHaveCount(1)
        ->and($row['_table']['actions'][0])
        ->key->toBe('edit')
        ->endpoint->toBe(['method' => 'get', 'url' => '/topics/1']);
});

it('supports mapped and custom column sorts', function () {
    $mapped = BooleanColumn::make('is_featured')
        ->sortable()
        ->mapAs([false => 'Z', true => 'A'])
        ->sortUsingMap();

    $query = TopicRecord::query();
    $mapped->applySort($query, 'asc');

    expect($query->pluck('name')->all())->toBe(['Beta', 'Gamma', 'Alpha']);

    $custom = NumberColumn::make('score')->sortable()->sortUsing(
        fn (Builder $query, SortDirection $direction) => $query->orderBy('score', $direction->value),
    );
    $query = TopicRecord::query();
    $custom->applySort($query, 'desc');

    expect($query->pluck('name')->all())->toBe(['Beta', 'Gamma', 'Alpha']);
});

it('serializes URL navigation options for clickable columns', function () {
    $topic = TopicRecord::query()->firstOrFail();
    $url = TextColumn::make('name')->url(
        fn (TopicRecord $model, Url $url) => $url
            ->to("/topics/{$model->id}")
            ->openInNewTab()
            ->preserveScroll(false),
    )->resolveUrl($topic);

    expect($url)->toMatchArray([
        'url' => '/topics/1',
        'newTab' => true,
        'preserveScroll' => false,
    ]);
});

it('formats date columns on the server', function () {
    $topic = new TopicRecord;
    $topic->setAttribute('published_at', Carbon::parse('2026-08-10 15:30:00'));

    expect(DateColumn::make('published_at')->format('d/m/Y')->resolveValue($topic))
        ->toBe('10/08/2026');
});

it('serializes presentation options and maps column values', function () {
    $topic = TopicRecord::query()->firstOrFail();
    $column = TextColumn::make(
        'name',
        sortable: true,
        wrap: true,
        truncate: 2,
        mapAs: fn (string $value) => strtoupper($value),
        tooltip: 'Public topic name',
        headerClass: ['font-semibold', 'text-primary'],
        cellClass: 'max-w-sm',
    );

    expect($column->resolveValue($topic))->toBe('ALPHA')
        ->and($column->toArray())->toMatchArray([
            'sortable' => true,
            'wrap' => true,
            'truncate' => 2,
            'tooltip' => 'Public topic name',
            'headerClass' => 'font-semibold text-primary',
            'cellClass' => 'max-w-sm',
        ]);
});

it('resolves badge presentation metadata per row', function () {
    $topic = TopicRecord::query()->where('is_featured', true)->firstOrFail();
    $column = BadgeColumn::make('is_featured')
        ->mapAs([1 => 'Featured', 0 => 'Regular'])
        ->variant([1 => Variant::Success, 0 => Variant::Default])
        ->icon([1 => 'Star', 0 => null])
        ->badgeClass([1 => 'ring-1 ring-emerald-500', 0 => null]);

    expect($column->resolveValue($topic))->toBe('Featured')
        ->and($column->resolveCellMeta($topic))->toBe([
            'variant' => 'success',
            'icon' => 'Star',
            'badgeClass' => 'ring-1 ring-emerald-500',
        ]);
});

it('serializes configurable images for any column', function () {
    $topic = TopicRecord::query()->firstOrFail();
    $topic->setAttribute('avatar_url', [
        'https://cdn.example.test/one.png',
        'https://cdn.example.test/two.png',
        'https://cdn.example.test/three.png',
    ]);
    $column = TextColumn::make('name')->image('avatar_url', fn ($image) => $image
        ->rounded()
        ->large()
        ->limit(2)
        ->alt('Topic avatar'));
    $imageColumn = ImageColumn::make('avatar_url');

    expect($column->resolveCellMeta($topic)['image'])->toMatchArray([
        'urls' => ['https://cdn.example.test/one.png', 'https://cdn.example.test/two.png'],
        'overflow' => 1,
        'size' => 'large',
        'rounded' => true,
        'alt' => 'Topic avatar',
    ])
        ->and($imageColumn->resolveCellMeta($topic)['image']['urls'])
        ->toBe([
            'https://cdn.example.test/one.png',
            'https://cdn.example.test/two.png',
            'https://cdn.example.test/three.png',
        ]);
});

it('can be passed directly as an inertia prop', function () {
    Route::get('/topic-table', fn () => Inertia::render('Topics', [
        'topics' => (new TopicsTable)->reloadProps('featuredCount'),
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get('/topic-table')
        ->assertOk()
        ->assertJsonPath('component', 'Topics')
        ->assertJsonPath('props.topics.schemaVersion', 1)
        ->assertJsonPath('props.topics.name', 'topics')
        ->assertJsonCount(3, 'props.topics.results.data');
});

it('serializes select filter as a deprecated alias of set filter', function () {
    $resource = (new TopicsTable)->resolve(tableRequest())->toArray();
    $status = collect($resource['filters'])->firstWhere('attribute', 'status');

    expect($status['type'])->toBe('select')
        ->and($status['clauses'])->toBe(['equals'])
        ->and($status['options'])->toBe([
            ['value' => 'featured', 'label' => 'Featured'],
            ['value' => 'regular', 'label' => 'Regular'],
        ]);
});

it('drops an undeclared filter attribute before it ever reaches the query builder', function () {
    $resource = (new TopicsTable)->resolve(tableRequest([
        'filters' => [
            'is_admin' => ['enabled' => true, 'clause' => 'equals', 'value' => true],
        ],
    ]))->toArray();

    expect($resource['state']['filters'])->not->toHaveKey('is_admin')
        ->and($resource['results']['total'])->toBe(3);
});

it('falls back to the default sort when an undeclared or malicious sort value is requested', function () {
    $resource = (new TopicsTable)->resolve(tableRequest([
        'sort' => 'id); drop table topics; --',
    ]))->toArray();

    expect($resource['state']['sort'])->toBe('name')
        ->and($resource['results']['total'])->toBe(3);
});

it('treats sql metacharacters in the global search term as a literal value, not sql', function () {
    $resource = (new TopicsTable)->resolve(tableRequest([
        'search' => "' OR '1'='1",
    ]))->toArray();

    expect($resource['results']['data'])->toBe([])
        ->and($resource['results']['total'])->toBe(0);
});

it('treats sql metacharacters in a text filter value as a literal value, not sql', function () {
    $resource = (new AdvancedFilterTopicsTable)->resolve(tableRequest([
        'filters' => [
            'name' => ['enabled' => true, 'clause' => 'contains', 'value' => "'; DROP TABLE topics; --"],
        ],
    ]))->toArray();

    expect($resource['results']['data'])->toBe([])
        ->and($resource['results']['total'])->toBe(0);

    // The injection attempt must not have actually dropped the table.
    expect((new TopicsTable)->resolve(tableRequest())->toArray()['results']['total'])->toBe(3);
});

it('binds a numeric filter value as a query parameter instead of interpolating it', function () {
    $resource = (new AdvancedFilterTopicsTable)->resolve(tableRequest([
        'filters' => [
            'score' => ['enabled' => true, 'clause' => 'equals', 'value' => '10 OR 1=1'],
        ],
    ]))->toArray();

    // A non-numeric value normalizes to null and the filter is dropped entirely,
    // rather than reaching the query as a raw, un-bound fragment.
    expect($resource['state']['filters']['score']['enabled'])->toBeFalse()
        ->and($resource['results']['total'])->toBe(3);
});
