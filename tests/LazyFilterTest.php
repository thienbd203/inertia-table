<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\Clause;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Table;

class LazyFilterCategory extends Model
{
    protected $table = 'lazy_filter_categories';

    protected $guarded = [];

    public $timestamps = false;
}

class LazyFilterProduct extends Model
{
    protected $table = 'lazy_filter_products';

    protected $guarded = [];

    public $timestamps = false;
}

class LazyProductsTable extends Table
{
    protected ?string $name = 'lazy_products';

    public function query(): Builder
    {
        return LazyFilterProduct::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name')];
    }

    public function filters(): array
    {
        return [
            SetFilter::make('category_id', 'Category')
                ->pluckOptionsFromModel(LazyFilterCategory::class, 'name')
                ->lazy()
                ->multiple()
                ->clauses([Clause::In]),
        ];
    }
}

beforeEach(function () {
    Schema::create('lazy_filter_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('lazy_filter_products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('category_id');
    });

    LazyFilterCategory::query()->insert([
        ['id' => 1, 'name' => 'Books'],
        ['id' => 2, 'name' => 'Games'],
    ]);
    LazyFilterProduct::query()->insert([
        ['name' => 'Laravel Book', 'category_id' => 1],
        ['name' => 'Board Game', 'category_id' => 2],
    ]);
});

function lazyFilterResource(bool $loadOptions = false, array $filters = []): array
{
    $request = Request::create('/products', 'GET', [
        'table' => ['lazy_products' => ['filters' => $filters]],
    ]);

    if ($loadOptions) {
        $request->headers->set(
            'X-Musing-Inertia-Table-Lazy-Filters',
            json_encode(['lazy_products' => ['category_id']], JSON_THROW_ON_ERROR),
        );
    }

    return (new LazyProductsTable)->resolve($request)->toArray();
}

function lazyCategoryDefinition(bool $loadOptions = false, array $filters = []): array
{
    return collect(lazyFilterResource($loadOptions, $filters)['filters'])
        ->firstWhere('attribute', 'category_id');
}

it('omits lazy options until the client requests the filter', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $definition = lazyCategoryDefinition();
    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($definition)
        ->options->toBe([])
        ->lazy->toBeTrue()
        ->lazyLoaded->toBeFalse()
        ->and(collect($queries)->pluck('query')->join(' '))
        ->not->toContain('lazy_filter_categories');
});

it('loads all options on the first lazy partial reload', function () {
    expect(lazyCategoryDefinition(true))
        ->options->toBe([
            ['value' => 1, 'label' => 'Books'],
            ['value' => 2, 'label' => 'Games'],
        ])
        ->lazy->toBeTrue()
        ->lazyLoaded->toBeTrue();
});

it('still validates lazy filter values against the declared option source', function () {
    $valid = lazyFilterResource(false, [
        'category_id' => [
            'enabled' => true,
            'clause' => 'in',
            'value' => [2],
        ],
    ]);
    $invalid = lazyFilterResource(false, [
        'category_id' => [
            'enabled' => true,
            'clause' => 'in',
            'value' => [999],
        ],
    ]);

    expect(array_column($valid['results']['data'], 'name'))->toBe(['Board Game'])
        ->and($invalid['state']['filters']['category_id'])->toBe([
            'enabled' => false,
            'clause' => 'in',
            'value' => null,
        ]);
});
