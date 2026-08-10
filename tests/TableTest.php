<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Toolbelt\InertiaTable\Columns\BooleanColumn;
use Toolbelt\InertiaTable\Columns\NumberColumn;
use Toolbelt\InertiaTable\Columns\TextColumn;
use Toolbelt\InertiaTable\Filters\BooleanFilter;
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
            TextColumn::make('name', 'Name')->searchable()->sortable(),
            NumberColumn::make('score', 'Score')->sortable(),
            BooleanColumn::make('is_featured', 'Featured'),
        ];
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
        ->columns->toHaveCount(4)
        ->filters->toHaveCount(3)
        ->debounceTime->toBe(300)
        ->perPageOptions->toBe([10, 25, 50, 100])
        ->state->sort->toBe('name')
        ->results->total->toBe(3)
        ->and($resource['columns'][0])
        ->toMatchArray([
            'attribute' => 'id',
            'type' => 'number',
            'sortable' => true,
            'toggleable' => false,
        ]);
});

it('searches only searchable columns', function () {
    $resource = (new TopicsTable)->resolve(tableRequest(['search' => 'amm']))->toArray();

    expect($resource['results']['data'])
        ->toHaveCount(1)
        ->and($resource['results']['data'][0]['name'])->toBe('Gamma');
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
        'filters' => ['name' => 'amm'],
    ]))->toArray();

    expect($resource['results']['data'])
        ->toHaveCount(1)
        ->and($resource['results']['data'][0]['name'])->toBe('Gamma')
        ->and($resource['state']['filters'])->toBe(['name' => 'amm']);
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
        'filters' => ['status' => 'featured'],
    ]))->toArray();
    $invalid = (new TopicsTable)->resolve(tableRequest([
        'filters' => ['status' => 'missing'],
    ]))->toArray();

    expect(array_column($featured['results']['data'], 'name'))
        ->toBe(['Beta', 'Gamma'])
        ->and($featured['state']['filters'])->toBe(['status' => 'featured'])
        ->and($invalid['results']['total'])->toBe(3)
        ->and($invalid['state']['filters'])->toBe([]);
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

    expect($resource['reloadProps'])->toBe(['featuredCount', 'trashedCount']);
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
