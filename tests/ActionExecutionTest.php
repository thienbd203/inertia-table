<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Selection;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;

class ActionTopicRecord extends Model
{
    protected $table = 'action_topics';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'archived' => 'boolean',
        ];
    }
}

class ActionTopicsTable extends Table
{
    protected ?string $name = 'action_topics';

    public function query(): Builder
    {
        return ActionTopicRecord::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name')->searchable()];
    }

    public function filters(): array
    {
        return [BooleanFilter::make('is_featured')];
    }

    public function actions(): array
    {
        return [
            Action::make('feature')
                ->rowAndBulk()
                ->handle(fn (ActionTopicRecord $topic) => $topic->update(['is_featured' => true])),
            Action::make('archive-matching')
                ->bulk()
                ->handleSelection(fn (Selection $selection) => $selection->query()->update(['archived' => true])),
            Action::make('disabled')
                ->row()
                ->disabled()
                ->disabledTooltip('Topics are locked.')
                ->handle(fn (ActionTopicRecord $topic) => $topic->update(['archived' => true])),
            Action::make('hidden')
                ->row()
                ->hidden()
                ->handle(fn (ActionTopicRecord $topic) => $topic->update(['archived' => true])),
            Action::make('external')
                ->row()
                ->endpoint('patch', fn (ActionTopicRecord $topic) => "/topics/{$topic->getKey()}/external"),
        ];
    }
}

beforeEach(function () {
    Schema::create('action_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('is_featured')->default(false);
        $table->boolean('archived')->default(false);
    });

    ActionTopicRecord::query()->insert([
        ['name' => 'Alpha', 'is_featured' => false, 'archived' => false],
        ['name' => 'Beta', 'is_featured' => true, 'archived' => false],
        ['name' => 'Gamma', 'is_featured' => true, 'archived' => false],
    ]);
});

function actionTableResource(): array
{
    return (new ActionTopicsTable)->resolve()->toArray();
}

function bulkActionEndpoint(string $key): string
{
    $action = collect(actionTableResource()['actions'])->firstWhere('key', $key);

    return $action['endpoint']['url'];
}

function rowActionEndpoint(string $key, int $row = 0): string
{
    $action = collect(actionTableResource()['results']['data'][$row]['_table']['actions'])
        ->firstWhere('key', $key);

    return $action['endpoint']['url'];
}

it('serializes server handlers as signed internal post endpoints', function () {
    $resource = actionTableResource();
    $feature = collect($resource['results']['data'][0]['_table']['actions'])->firstWhere('key', 'feature');
    $external = collect($resource['results']['data'][0]['_table']['actions'])->firstWhere('key', 'external');

    expect($feature['endpoint']['method'])->toBe('post')
        ->and($feature['endpoint']['url'])->toStartWith('/_inertia-table/actions/')
        ->and($feature['endpoint']['url'])->toContain('signature=')
        ->and($external['endpoint'])->toBe([
            'method' => 'patch',
            'url' => '/topics/1/external',
        ])
        ->and(collect($resource['results']['data'][0]['_table']['actions'])->pluck('key')->all())
        ->not->toContain('hidden');
});

it('executes a row handler against the resolved table model', function () {
    $this->from('/topics')
        ->post(rowActionEndpoint('feature'), ['id' => 1])
        ->assertRedirect('/topics');

    expect(ActionTopicRecord::query()->findOrFail(1)->is_featured)->toBeTrue();
});

it('executes a per-model handler for explicit bulk keys', function () {
    $this->post(bulkActionEndpoint('feature'), ['ids' => [1, 3]])
        ->assertRedirect();

    expect(ActionTopicRecord::query()->where('is_featured', true)->pluck('id')->all())
        ->toBe([1, 2, 3]);
});

it('executes one selection handler for all filtered matches except exclusions', function () {
    $this->post(bulkActionEndpoint('archive-matching'), [
        'ids' => [],
        'selection' => [
            'all' => true,
            'keys' => [],
            'except' => [2],
            'table' => 'action_topics',
            'state' => [
                'search' => 'a',
                'filters' => [
                    'is_featured' => [
                        'enabled' => true,
                        'clause' => 'is_true',
                        'value' => null,
                    ],
                ],
            ],
        ],
    ])->assertRedirect();

    expect(ActionTopicRecord::query()->where('archived', true)->pluck('id')->all())
        ->toBe([3]);
});

it('rejects a tampered managed action url', function () {
    $url = str_replace('archive-matching', 'feature', bulkActionEndpoint('archive-matching'));

    $this->post($url, ['ids' => [1]])->assertForbidden();

    expect(ActionTopicRecord::query()->findOrFail(1)->is_featured)->toBeFalse();
});

it('rechecks disabled and hidden action state on execution', function () {
    $this->postJson(rowActionEndpoint('disabled'), ['id' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('action');

    $hiddenUrl = URL::signedRoute('inertia-table.actions', [
        'table' => TableReference::encode(ActionTopicsTable::class),
        'action' => 'hidden',
    ], absolute: false);

    $this->post($hiddenUrl, ['id' => 1])->assertForbidden();

    expect(ActionTopicRecord::query()->findOrFail(1)->archived)->toBeFalse();
});

it('rejects a selection descriptor from another table', function () {
    $this->postJson(bulkActionEndpoint('archive-matching'), [
        'selection' => [
            'all' => true,
            'keys' => [],
            'except' => [],
            'table' => 'another_table',
            'state' => [],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('selection.table');
});

it('does not allow endpoints and handlers on the same action', function () {
    expect(fn () => Action::make('invalid')->endpoint('post', '/invalid')->handle(fn () => null))
        ->toThrow(LogicException::class)
        ->and(fn () => Action::make('invalid')->handle(fn () => null)->endpoint('post', '/invalid'))
        ->toThrow(LogicException::class);
});
