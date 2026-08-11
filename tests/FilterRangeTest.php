<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Toolbelt\InertiaTable\Columns\DateColumn;
use Toolbelt\InertiaTable\Columns\NumberColumn;
use Toolbelt\InertiaTable\Columns\TextColumn;
use Toolbelt\InertiaTable\Filters\DateFilter;
use Toolbelt\InertiaTable\Filters\NumericFilter;
use Toolbelt\InertiaTable\Table;

class ScoreRecord extends Model
{
    protected $table = 'score_records';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }
}

class RangeFilterTable extends Table
{
    public function query(): Builder
    {
        return ScoreRecord::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->sortable(),
            NumberColumn::make('score', 'Score'),
            DateColumn::make('published_at', 'Published'),
        ];
    }

    public function filters(): array
    {
        return [
            NumericFilter::make('score'),
            DateFilter::make('published_at'),
        ];
    }
}

beforeEach(function () {
    Schema::create('score_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('score');
        $table->date('published_at');
    });

    ScoreRecord::query()->insert([
        ['name' => 'Alpha', 'score' => 10, 'published_at' => '2024-01-01'],
        ['name' => 'Beta', 'score' => 20, 'published_at' => '2024-02-01'],
        ['name' => 'Gamma', 'score' => 30, 'published_at' => '2024-03-01'],
        ['name' => 'Delta', 'score' => 40, 'published_at' => '2024-04-01'],
    ]);
});

function rangeRequest(array $filters): Request
{
    return Request::create('/range-filter', 'GET', [
        'table' => ['range_filter' => ['filters' => $filters]],
    ]);
}

it('applies a numeric between clause across the declared range', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'score' => ['enabled' => true, 'clause' => 'between', 'value' => [15, 35]],
    ]))->toArray();

    expect(array_column($resource['results']['data'], 'name'))->toBe(['Beta', 'Gamma']);
});

it('applies a numeric not_between clause outside the declared range', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'score' => ['enabled' => true, 'clause' => 'not_between', 'value' => [15, 35]],
    ]))->toArray();

    expect(array_column($resource['results']['data'], 'name'))->toBe(['Alpha', 'Delta']);
});

it('ignores an incomplete numeric range value', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'score' => ['enabled' => true, 'clause' => 'between', 'value' => [15]],
    ]))->toArray();

    expect($resource['state']['filters']['score']['enabled'])->toBeFalse()
        ->and($resource['results']['total'])->toBe(4);
});

it('ignores a non numeric numeric range value', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'score' => ['enabled' => true, 'clause' => 'between', 'value' => ['a', 'b']],
    ]))->toArray();

    expect($resource['state']['filters']['score']['enabled'])->toBeFalse()
        ->and($resource['results']['total'])->toBe(4);
});

it('applies a date between clause across the declared range', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'published_at' => ['enabled' => true, 'clause' => 'between', 'value' => ['2024-01-15', '2024-03-15']],
    ]))->toArray();

    expect(array_column($resource['results']['data'], 'name'))->toBe(['Beta', 'Gamma']);
});

it('applies a date not_between clause outside the declared range', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'published_at' => ['enabled' => true, 'clause' => 'not_between', 'value' => ['2024-01-15', '2024-03-15']],
    ]))->toArray();

    expect(array_column($resource['results']['data'], 'name'))->toBe(['Alpha', 'Delta']);
});

it('ignores an incomplete date range value', function () {
    $resource = (new RangeFilterTable)->resolve(rangeRequest([
        'published_at' => ['enabled' => true, 'clause' => 'between', 'value' => ['2024-01-15']],
    ]))->toArray();

    expect($resource['state']['filters']['published_at']['enabled'])->toBeFalse()
        ->and($resource['results']['total'])->toBe(4);
});
