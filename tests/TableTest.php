<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Toolbelt\InertiaTable\Actions\Action;
use Toolbelt\InertiaTable\Columns\ActionColumn;
use Toolbelt\InertiaTable\Columns\BadgeColumn;
use Toolbelt\InertiaTable\Columns\BooleanColumn;
use Toolbelt\InertiaTable\Columns\DateColumn;
use Toolbelt\InertiaTable\Columns\ImageColumn;
use Toolbelt\InertiaTable\Columns\NumberColumn;
use Toolbelt\InertiaTable\Columns\TextColumn;
use Toolbelt\InertiaTable\Filters\BooleanFilter;
use Toolbelt\InertiaTable\Filters\NumericFilter;
use Toolbelt\InertiaTable\Filters\SelectFilter;
use Toolbelt\InertiaTable\Filters\TextFilter;
use Toolbelt\InertiaTable\Table;

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
            'hasToggleableColumns' => true,
        ])
        ->search->toBe(['name'])
        ->options->toBe([
            'debounceTime' => 300,
            'perPage' => [10, 25, 50, 100],
            'reloadProps' => [],
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
        'variant' => 'destructive',
        'icon' => 'Trash',
        'labelHidden' => true,
        'tooltip' => 'Delete selected topics',
        'confirmation' => [
            'title' => 'Delete topics',
            'message' => 'This action cannot be undone.',
            'confirmLabel' => 'Confirm',
            'cancelLabel' => 'Cancel',
        ],
        'endpoint' => ['method' => 'delete', 'url' => '/topics/bulk'],
        'meta' => [],
    ]);
});

it('resolves row links, cell links, and row actions without frontend slots', function () {
    $resource = (new TopicsTable)->resolve(tableRequest())->toArray();
    $row = $resource['results']['data'][0];

    expect($row['_table'])
        ->url->toBe('/topics/1')
        ->columns->toBe(['name' => '/topics/1'])
        ->actions->toHaveCount(1)
        ->and($row['_table']['actions'][0])
        ->key->toBe('edit')
        ->endpoint->toBe(['method' => 'get', 'url' => '/topics/1']);
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
        ->variant([1 => 'success', 0 => 'default'])
        ->icon([1 => 'Star', 0 => null]);

    expect($column->resolveValue($topic))->toBe('Featured')
        ->and($column->resolveCellMeta($topic))->toBe([
            'variant' => 'success',
            'icon' => 'Star',
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
