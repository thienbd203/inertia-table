<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\Clause;
use Musing\InertiaTable\Filters\FilterOptionRequest;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Table;

class RemoteFilterCategory extends Model
{
    protected $table = 'remote_filter_categories';

    protected $guarded = [];

    public $timestamps = false;
}

class RemoteFilterProduct extends Model
{
    protected $table = 'remote_filter_products';

    protected $guarded = [];

    public $timestamps = false;
}

class RemoteProductsTable extends Table
{
    protected ?string $name = 'remote_products';

    public static bool $allowOptions = true;

    public function query(): Builder
    {
        return RemoteFilterProduct::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name')->searchable()];
    }

    public function filters(): array
    {
        return [
            SetFilter::make('status')
                ->options(['active' => 'Active', 'archived' => 'Archived'])
                ->clauses([Clause::Equals]),
            SetFilter::make('category_id', 'Category')
                ->optionsUsing(fn (FilterOptionRequest $request) => RemoteFilterCategory::query()
                    ->when(
                        $request->dependency('status'),
                        fn (Builder $query, mixed $status) => $query->where('available_status', $status),
                    ))
                ->optionValue('id')
                ->optionLabel('name')
                ->searchableOptions()
                ->dependsOn(['status'])
                ->withCounts()
                ->optionPageSize(2)
                ->clauses([Clause::Equals])
                ->authorizeOptionsUsing(fn () => self::$allowOptions),
        ];
    }
}

beforeEach(function () {
    RemoteProductsTable::$allowOptions = true;

    Schema::create('remote_filter_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('available_status');
    });
    Schema::create('remote_filter_products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('category_id');
        $table->string('status');
    });

    RemoteFilterCategory::query()->insert([
        ['id' => 1, 'name' => 'Alpha', 'available_status' => 'active'],
        ['id' => 2, 'name' => '100% Genuine', 'available_status' => 'active'],
        ['id' => 3, 'name' => '100X', 'available_status' => 'active'],
        ['id' => 4, 'name' => 'Restored outside first page', 'available_status' => 'active'],
        ['id' => 5, 'name' => 'Archived only', 'available_status' => 'archived'],
    ]);
    RemoteFilterProduct::query()->insert([
        ['name' => 'Alpha active one', 'category_id' => 1, 'status' => 'active'],
        ['name' => 'Alpha active two', 'category_id' => 1, 'status' => 'active'],
        ['name' => 'Alpha archived', 'category_id' => 1, 'status' => 'archived'],
        ['name' => 'Percent active', 'category_id' => 2, 'status' => 'active'],
        ['name' => 'X active one', 'category_id' => 3, 'status' => 'active'],
        ['name' => 'X active two', 'category_id' => 3, 'status' => 'active'],
        ['name' => 'X active three', 'category_id' => 3, 'status' => 'active'],
        ['name' => 'Restored archived one', 'category_id' => 4, 'status' => 'archived'],
        ['name' => 'Restored archived two', 'category_id' => 4, 'status' => 'archived'],
    ]);
});

function remoteFilterResource(array $filters = []): array
{
    return (new RemoteProductsTable)->resolve(Request::create('/products', 'GET', [
        'table' => ['remote_products' => ['filters' => $filters]],
    ]))->toArray();
}

function remoteCategoryDefinition(array $filters = []): array
{
    return collect(remoteFilterResource($filters)['filters'])
        ->firstWhere('attribute', 'category_id');
}

it('serializes a signed remote option contract without the option dataset', function () {
    $definition = remoteCategoryDefinition();

    expect($definition['options'])->toBe([])
        ->and($definition['remote']['endpoint'])->toContain('/_inertia-table/filter-options/')
        ->and($definition['remote']['searchable'])->toBeTrue()
        ->and($definition['remote']['dependsOn'])->toBe(['status'])
        ->and($definition['remote']['withCounts'])->toBeTrue()
        ->and($definition['remote']['perPage'])->toBe(2);
});

it('rejects unsigned, tampered, unauthorized, and unknown option requests', function () {
    $endpoint = remoteCategoryDefinition()['remote']['endpoint'];

    $this->postJson(str_replace('category_id', 'status', $endpoint))->assertForbidden();
    $this->postJson(preg_replace('/signature=[^&]+/', 'signature=bad', $endpoint))->assertForbidden();

    RemoteProductsTable::$allowOptions = false;
    $this->postJson($endpoint)->assertForbidden();
});

it('searches literally, allowlists dependencies, and paginates with opaque cursors', function () {
    $endpoint = remoteCategoryDefinition()['remote']['endpoint'];
    $state = [
        'filters' => [
            'status' => ['enabled' => true, 'clause' => 'equals', 'value' => 'active'],
            'not_declared' => ['enabled' => true, 'clause' => 'equals', 'value' => 'secret'],
        ],
    ];

    $literal = $this->postJson($endpoint, [
        'search' => '%',
        'state' => $state,
    ])->assertOk()->json();

    expect(array_column($literal['options'], 'label'))->toBe(['100% Genuine']);

    $first = $this->postJson($endpoint, ['state' => $state])
        ->assertOk()
        ->json();
    expect(array_column($first['options'], 'value'))->toBe([1, 2])
        ->and($first['nextCursor'])->toBeString()->not->toBeEmpty();

    $second = $this->postJson($endpoint, [
        'state' => $state,
        'cursor' => $first['nextCursor'],
    ])->assertOk()->json();
    expect(array_column($second['options'], 'value'))->toBe([3, 4])
        ->and(array_column($second['options'], 'label'))->not->toContain('Archived only');
});

it('hydrates selected labels outside the current page and when restoring state', function () {
    $filters = [
        'status' => ['enabled' => true, 'clause' => 'equals', 'value' => 'active'],
        'category_id' => ['enabled' => true, 'clause' => 'equals', 'value' => 4],
    ];
    $definition = remoteCategoryDefinition($filters);

    expect($definition['options'])->toContainEqual([
        'value' => 4,
        'label' => 'Restored outside first page',
    ]);

    $response = $this->postJson($definition['remote']['endpoint'], [
        'state' => ['filters' => $filters],
        'selected' => [4],
    ])->assertOk()->json();

    expect($response['selected'])->toContainEqual([
        'value' => 4,
        'label' => 'Restored outside first page',
        'count' => 0,
    ]);
});

it('hydrates a selected label even when a dependency excludes it from available options', function () {
    $filters = [
        'status' => ['enabled' => true, 'clause' => 'equals', 'value' => 'active'],
        'category_id' => ['enabled' => true, 'clause' => 'equals', 'value' => 5],
    ];

    $definition = remoteCategoryDefinition($filters);

    expect($definition['options'])->toContainEqual([
        'value' => 5,
        'label' => 'Archived only',
    ]);
});

it('calculates facets from search and other filters while excluding itself', function () {
    $filters = [
        'status' => ['enabled' => true, 'clause' => 'equals', 'value' => 'active'],
        'category_id' => ['enabled' => true, 'clause' => 'equals', 'value' => 1],
    ];
    $definition = remoteCategoryDefinition($filters);
    $response = $this->postJson($definition['remote']['endpoint'], [
        'state' => ['filters' => $filters],
    ])->assertOk()->json();
    $counts = collect($response['options'])->mapWithKeys(
        fn (array $option) => [(string) $option['value'] => $option['count']],
    )->all();

    expect($counts)->toBe(['1' => 2, '2' => 1]);
});

it('loads an option page and its facets with a bounded query count', function () {
    $endpoint = remoteCategoryDefinition()['remote']['endpoint'];

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->postJson($endpoint)->assertOk();
    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($queries)->toHaveCount(2);
});
