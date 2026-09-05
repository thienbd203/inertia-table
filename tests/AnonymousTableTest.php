<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\AnonymousTable;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\EmptyState;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\PaginationType;
use Musing\InertiaTable\Table;
use Spatie\QueryBuilder\QueryBuilder;

class AnonymousTopicRecord extends Model
{
    protected $table = 'anonymous_topics';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('anonymous_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('score')->default(0);
        $table->boolean('published')->default(false);
    });
});

it('builds an anonymous table from a model class using the public options', function () {
    AnonymousTopicRecord::query()->create(['name' => 'Alpha', 'score' => 10, 'published' => false]);
    AnonymousTopicRecord::query()->create(['name' => 'Beta', 'score' => 30, 'published' => true]);
    AnonymousTopicRecord::query()->create(['name' => 'Gamma', 'score' => 20, 'published' => true]);

    $table = Table::build(
        resource: AnonymousTopicRecord::class,
        columns: [
            TextColumn::make('name')->searchable(),
            NumberColumn::make('score')->sortable(),
        ],
        filters: [BooleanFilter::make('published')],
        search: ['name'],
        name: 'inline_topics',
        pagination: false,
        debounceTime: 125,
        perPageOptions: [2, 5],
        defaultSort: '-score',
        transformModelUsing: fn (AnonymousTopicRecord $topic) => [
            ...$topic->toArray(),
            'summary' => "{$topic->name}:{$topic->score}",
        ],
        withQueryBuilder: function (QueryBuilder $query): void {
            $query->getEloquentBuilder()->where('score', '>=', 20);
        },
        emptyState: EmptyState::make('No inline topics'),
        stickyHeader: true,
        defaultPerPage: 2,
        stickyBackdropFilter: false,
        columnResizing: false,
        columnReordering: false,
        stickyFooter: true,
    );

    $resource = $table->resolve(Request::create('/', 'GET'))->toArray();

    expect($table)->toBeInstanceOf(AnonymousTable::class)
        ->and($resource['name'])->toBe('inline_topics')
        ->and($resource['search'])->toBe(['name'])
        ->and($resource['actions'])->toBe([])
        ->and($resource['exports'])->toBe([])
        ->and($resource['capabilities']['paginated'])->toBeFalse()
        ->and($resource['capabilities']['hasEmptyState'])->toBeTrue()
        ->and($resource['options'])->toMatchArray([
            'debounceTime' => 125,
            'perPage' => [2, 5],
            'stickyHeader' => true,
            'stickyFooter' => true,
            'stickyBackdropFilter' => false,
            'columnResizing' => false,
            'columnReordering' => false,
        ])
        ->and($resource['state']['sort'])->toBe('-score')
        ->and($resource['state']['perPage'])->toBe(2)
        ->and($resource['results'])->toMatchArray([
            'currentPage' => 1,
            'from' => 1,
            'lastPage' => 1,
            'links' => [],
            'perPage' => 2,
            'to' => 2,
            'total' => 2,
        ])
        ->and(array_column($resource['results']['data'], 'name'))->toBe(['Beta', 'Gamma'])
        ->and(array_column($resource['results']['data'], 'summary'))->toBe(['Beta:30', 'Gamma:20']);
});

it('clones an Eloquent builder before resolving an anonymous table', function () {
    AnonymousTopicRecord::query()->create(['name' => 'Alpha', 'score' => 10]);
    AnonymousTopicRecord::query()->create(['name' => 'Beta', 'score' => 20]);
    $builder = AnonymousTopicRecord::query()->where('score', '>=', 10);
    $table = Table::build(
        resource: $builder,
        columns: [NumberColumn::make('score')->sortable()],
        defaultSort: '-score',
    );

    $first = $table->resolve(Request::create('/', 'GET'))->toArray();
    $second = $table->resolve(Request::create('/', 'GET'))->toArray();

    expect($first['results']['total'])->toBe(2)
        ->and($second['results']['total'])->toBe(2)
        ->and($builder->getQuery()->orders)->toBeNull();
});

it('configures cursor pagination through the anonymous table builder', function () {
    AnonymousTopicRecord::query()->create(['name' => 'Alpha', 'score' => 10]);
    AnonymousTopicRecord::query()->create(['name' => 'Beta', 'score' => 20]);
    $table = Table::build(
        resource: AnonymousTopicRecord::class,
        columns: [NumberColumn::make('score')->sortable()],
        defaultSort: 'score',
        defaultPerPage: 1,
        perPageOptions: [1],
        paginationType: PaginationType::Cursor,
    );

    $resource = $table->resolve(Request::create('/', 'GET'))->toArray();

    expect($resource['options']['paginationType'])->toBe('cursor')
        ->and($resource['results']['total'])->toBeNull()
        ->and($resource['results']['nextCursor'])->not->toBeNull();
});

it('skips the paginator count query in simple and cursor modes', function () {
    AnonymousTopicRecord::query()->create(['name' => 'Alpha', 'score' => 10]);
    AnonymousTopicRecord::query()->create(['name' => 'Beta', 'score' => 20]);

    $resolveWith = function (PaginationType $type): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Table::build(
            resource: AnonymousTopicRecord::class,
            columns: [NumberColumn::make('score')->sortable()],
            defaultSort: 'score',
            defaultPerPage: 1,
            perPageOptions: [1],
            paginationType: $type,
        )->resolve(Request::create('/', 'GET'));

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($resolveWith(PaginationType::Full))->toBe(2)
        ->and($resolveWith(PaginationType::Simple))->toBe(1)
        ->and($resolveWith(PaginationType::Cursor))->toBe(1);
});

it('renders the anonymous empty state only when its base resource is empty', function () {
    $table = Table::build(
        resource: AnonymousTopicRecord::class,
        columns: [TextColumn::make('name')],
        emptyState: EmptyState::make('Nothing here'),
    );

    expect($table->resolve(Request::create('/', 'GET'))->toArray()['emptyState']['title'])
        ->toBe('Nothing here');
});

it('rejects invalid anonymous resource and pagination options', function () {
    expect(fn () => Table::build(resource: stdClass::class))
        ->toThrow(LogicException::class, 'Eloquent model class or builder')
        ->and(fn () => Table::build(
            resource: AnonymousTopicRecord::class,
            perPageOptions: [10, 0],
        ))->toThrow(LogicException::class, 'positive integers')
        ->and(fn () => Table::build(
            resource: AnonymousTopicRecord::class,
            perPageOptions: [],
        ))->toThrow(LogicException::class, 'at least one per-page option')
        ->and(fn () => Table::build(
            resource: AnonymousTopicRecord::class,
            perPageOptions: [10, 25],
            defaultPerPage: 50,
        ))->toThrow(LogicException::class, 'must be one of its per-page options')
        ->and(fn () => Table::build(
            resource: AnonymousTopicRecord::class,
            debounceTime: -1,
        ))->toThrow(LogicException::class, 'zero or greater');
});

it('requires anonymous transform callbacks to return arrays', function () {
    AnonymousTopicRecord::query()->create(['name' => 'Alpha']);
    $table = Table::build(
        resource: AnonymousTopicRecord::class,
        columns: [TextColumn::make('name')],
        transformModelUsing: fn (Model $model) => $model->getKey(),
    );

    expect(fn () => $table->resolve(Request::create('/', 'GET')))
        ->toThrow(LogicException::class, 'must return an array');
});
