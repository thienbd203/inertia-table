<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Table;

class SelectionTopicRecord extends Model
{
    protected $table = 'selection_topics';

    protected $guarded = [];

    public $timestamps = false;
}

class SelectionTopicsTable extends Table
{
    protected ?string $name = 'selection_topics';

    public function query(): Builder
    {
        return SelectionTopicRecord::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id'),
            TextColumn::make('name')->searchable(),
        ];
    }

    public function filters(): array
    {
        return [BooleanFilter::make('is_featured')];
    }
}

class ConstrainedSelectionTopicsTable extends SelectionTopicsTable
{
    public function query(): Builder
    {
        return parent::query()->where('score', '>=', 20);
    }
}

beforeEach(function () {
    Schema::create('selection_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('score');
        $table->boolean('is_featured');
    });

    SelectionTopicRecord::query()->insert([
        ['name' => 'Alpha', 'score' => 10, 'is_featured' => false],
        ['name' => 'Beta', 'score' => 30, 'is_featured' => true],
        ['name' => 'Gamma', 'score' => 20, 'is_featured' => true],
    ]);
});

it('resolves an explicit selection to stable model keys', function () {
    $selection = (new SelectionTopicsTable)->selection([
        'all' => false,
        'keys' => [3, 1, 3],
        'except' => [2],
        'table' => 'selection_topics',
        'state' => ['search' => 'Beta'],
    ]);

    expect($selection)
        ->all->toBeFalse()
        ->keys->toBe([3, 1])
        ->except->toBe([])
        ->and($selection->query()->pluck('id')->all())->toBe([1, 3]);
});

it('resolves all matching through declared search and filters with exclusions', function () {
    $selection = (new SelectionTopicsTable)->selection([
        'all' => true,
        'keys' => [1],
        'except' => [2],
        'table' => 'selection_topics',
        'state' => [
            'search' => 'a',
            'filters' => [
                'is_featured' => [
                    'enabled' => true,
                    'clause' => 'is_true',
                    'value' => null,
                ],
                'not_declared' => [
                    'enabled' => true,
                    'clause' => 'equals',
                    'value' => 'anything',
                ],
            ],
        ],
    ]);

    expect($selection->keys)->toBe([])
        ->and($selection->state['filters'])->not->toHaveKey('not_declared')
        ->and($selection->query()->pluck('id')->all())->toBe([3])
        ->and($selection->count())->toBe(1);
});

it('never lets explicit keys bypass the table base query', function () {
    $selection = (new ConstrainedSelectionTopicsTable)->selection([
        'all' => false,
        'keys' => [1, 2, 3],
        'table' => 'selection_topics',
    ]);

    expect($selection->query()->pluck('id')->all())->toBe([2, 3]);
});

it('rejects malformed and cross-table selections', function (array $payload) {
    expect(fn () => (new SelectionTopicsTable)->selection($payload))
        ->toThrow(ValidationException::class);
})->with([
    'wrong table' => [[
        'all' => true,
        'keys' => [],
        'except' => [],
        'table' => 'another_table',
    ]],
    'empty explicit selection' => [[
        'all' => false,
        'keys' => [],
        'except' => [],
        'table' => 'selection_topics',
    ]],
    'non-scalar key' => [[
        'all' => false,
        'keys' => [['id' => 1]],
        'except' => [],
        'table' => 'selection_topics',
    ]],
]);
