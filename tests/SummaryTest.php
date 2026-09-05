<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\TextFilter;
use Musing\InertiaTable\Table;

class SummaryOrderRecord extends Model
{
    protected $table = 'summary_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class SummaryOrderLineRecord extends Model
{
    protected $table = 'summary_order_lines';

    protected $guarded = [];

    public $timestamps = false;
}

class SummaryOrdersTable extends Table
{
    protected ?string $name = 'orders';

    protected ?string $defaultSort = '-amount';

    protected ?int $perPage = 1;

    public function query(): Builder
    {
        return SummaryOrderRecord::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'Count')->summary('count'),
            TextColumn::make('name', 'Name')->searchable(),
            NumberColumn::make('amount', 'Total')->sortable()->summary('sum')->summaryFormat('#,##0.00'),
            NumberColumn::make('average_amount', 'Average')->summary('avg', 'amount'),
            NumberColumn::make('minimum_amount', 'Minimum')->summary('min', 'amount'),
            NumberColumn::make('maximum_amount', 'Maximum')->summary('max', 'amount'),
            NumberColumn::make('customers', 'Customers')->summary('count_distinct', 'customer_id'),
            NumberColumn::make('large_orders', 'Large orders')->summaryUsing(
                fn (Builder $query): int => (clone $query)->where('amount', '>=', 20)->count(),
            ),
        ];
    }

    public function filters(): array
    {
        return [TextFilter::make('category', 'Category')];
    }
}

class JoinedSummaryOrdersTable extends SummaryOrdersTable
{
    public function query(): Builder
    {
        return SummaryOrderRecord::query()
            ->leftJoin('summary_order_lines', 'summary_orders.id', '=', 'summary_order_lines.order_id');
    }

    public function columns(): array
    {
        return array_slice(parent::columns(), 0, 7);
    }
}

class GroupedSummaryOrdersTable extends Table
{
    protected ?string $name = 'grouped_orders';

    protected bool $pagination = false;

    public function query(): Builder
    {
        return SummaryOrderRecord::query()
            ->selectRaw('MIN(summary_orders.id) AS id, category, SUM(amount) AS amount')
            ->groupBy('category');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('category', 'Category'),
            NumberColumn::make('amount', 'Total')->summary('sum'),
        ];
    }
}

class PlainSummaryOrdersTable extends Table
{
    protected ?string $name = 'plain_orders';

    public function query(): Builder
    {
        return SummaryOrderRecord::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name', 'Name')];
    }
}

function summaryRequest(array $state = []): Request
{
    return Request::create('/', 'GET', ['table' => ['orders' => $state]]);
}

beforeEach(function () {
    Schema::create('summary_orders', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('category');
        $table->unsignedBigInteger('customer_id');
        $table->decimal('amount', 12, 2)->nullable();
    });
    Schema::create('summary_order_lines', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
    });

    SummaryOrderRecord::query()->insert([
        ['name' => 'Alpha', 'category' => 'retail', 'customer_id' => 1, 'amount' => 10.50],
        ['name' => 'Beta', 'category' => 'retail', 'customer_id' => 1, 'amount' => 20.25],
        ['name' => 'Gamma', 'category' => 'wholesale', 'customer_id' => 2, 'amount' => null],
        ['name' => 'Delta', 'category' => 'retail', 'customer_id' => 2, 'amount' => 5.25],
    ]);
    SummaryOrderLineRecord::query()->insert([
        ['order_id' => 1],
        ['order_id' => 1],
        ['order_id' => 2],
    ]);
});

it('resolves all built-in summaries for the filtered dataset in one query', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $resource = (new SummaryOrdersTable)->resolve(summaryRequest([
        'filters' => [
            'category' => ['enabled' => true, 'clause' => 'contains', 'value' => 'retail'],
        ],
        'perPage' => 1,
    ]))->toArray();
    $summaryQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query) => str_contains($query, 'inertia_table_summary'));

    expect($resource['results']['data'])->toHaveCount(1)
        ->and($resource['summaries']['id'])->toBe(3)
        ->and((float) $resource['summaries']['amount'])->toBe(36.0)
        ->and((float) $resource['summaries']['average_amount'])->toBe(12.0)
        ->and((float) $resource['summaries']['minimum_amount'])->toBe(5.25)
        ->and((float) $resource['summaries']['maximum_amount'])->toBe(20.25)
        ->and($resource['summaries']['customers'])->toBe(2)
        ->and($resource['summaries']['large_orders'])->toBe(1)
        ->and($resource['capabilities']['hasSummaries'])->toBeTrue()
        ->and($resource['columns'][2]['summary'])->toBe([
            'type' => 'sum',
            'format' => '#,##0.00',
        ])
        ->and($summaryQueries)->toHaveCount(1)
        ->and(strtolower($summaryQueries->first()))->not->toContain('order by');
});

it('returns SQL-standard empty aggregate values without reading the current page', function () {
    $resource = (new SummaryOrdersTable)->resolve(summaryRequest([
        'search' => 'missing',
    ]))->toArray();

    expect($resource['results']['data'])->toBe([])
        ->and($resource['summaries'])->toMatchArray([
            'id' => 0,
            'amount' => null,
            'average_amount' => null,
            'minimum_amount' => null,
            'maximum_amount' => null,
            'customers' => 0,
            'large_orders' => 0,
        ]);
});

it('deduplicates joined base models before aggregating', function () {
    $resource = (new JoinedSummaryOrdersTable)->resolve(summaryRequest())->toArray();

    expect($resource['results']['total'])->toBe(4)
        ->and($resource['summaries']['id'])->toBe(4)
        ->and((float) $resource['summaries']['amount'])->toBe(36.0)
        ->and($resource['summaries']['customers'])->toBe(2);
});

it('aggregates the rows produced by an application grouped query', function () {
    $resource = (new GroupedSummaryOrdersTable)->resolve(
        Request::create('/', 'GET'),
    )->toArray();

    expect($resource['results']['data'])->toHaveCount(2)
        ->and((float) $resource['summaries']['amount'])->toBe(36.0);
});

it('runs no summary query when none are declared', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $resource = (new PlainSummaryOrdersTable)->resolve(Request::create('/', 'GET'))->toArray();

    expect($resource['summaries'])->toBe([])
        ->and($resource['capabilities']['hasSummaries'])->toBeFalse()
        ->and(collect(DB::getQueryLog())->pluck('query')->contains(
            fn (string $query) => str_contains($query, 'inertia_table_summary'),
        ))->toBeFalse();
});
