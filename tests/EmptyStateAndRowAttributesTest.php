<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\EmptyState;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Url;
use Musing\InertiaTable\Variant;

class EmptyStateTopic extends Model
{
    protected $table = 'empty_state_topics';

    protected $guarded = [];

    public $timestamps = false;
}

class EmptyStateTopicsTable extends Table
{
    protected ?string $name = 'empty_state_topics';

    public function query(): Builder
    {
        return EmptyStateTopic::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id'),
            TextColumn::make('name')->searchable(),
            TextColumn::make('status'),
        ];
    }

    public function emptyState(): ?EmptyState
    {
        return EmptyState::make(
            title: 'No topics yet',
            message: 'Create the first topic.',
            dataAttributes: ['kind' => 'topics'],
            meta: ['surface' => 'admin'],
        )
            ->action(
                label: 'Create topic',
                url: fn (Url $url) => $url->to('/topics/create')->openInNewTab(),
                variant: Variant::Info,
                icon: 'Plus',
                buttonClass: 'create-topic',
                dataAttributes: ['intent' => 'create'],
                meta: ['source' => 'empty-state'],
            )
            ->action('Hidden', fn (Url $url) => $url->to('/hidden')->hidden());
    }

    protected function transform(Model $model): array
    {
        return [
            ...$model->toArray(),
            'status_label' => strtoupper((string) $model->getAttribute('status')),
        ];
    }

    public function dataAttributesForModel(Model $model, array $data): array
    {
        return [
            'record-id' => $model->getKey(),
            'status' => $data['status_label'],
        ];
    }
}

class InvalidRowAttributesTable extends EmptyStateTopicsTable
{
    public function dataAttributesForModel(Model $model, array $data): array
    {
        return ['selected' => true];
    }
}

beforeEach(function () {
    Schema::create('empty_state_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
    });
});

function emptyStateRequest(array $state = []): Request
{
    return Request::create('/topics', 'GET', [
        'table' => ['empty_state_topics' => $state],
    ]);
}

it('serializes schema v2 and a server-defined empty state only for a genuinely empty query', function () {
    $resource = (new EmptyStateTopicsTable)->resolve(emptyStateRequest())->toArray();

    expect($resource['schemaVersion'])->toBe(2)
        ->and($resource['capabilities']['hasEmptyState'])->toBeTrue()
        ->and($resource['emptyState'])->toMatchArray([
            'title' => 'No topics yet',
            'message' => 'Create the first topic.',
            'icon' => 'Inbox',
            'dataAttributes' => ['data-kind' => 'topics'],
            'meta' => ['surface' => 'admin'],
        ])
        ->and($resource['emptyState']['actions'])->toHaveCount(1)
        ->and($resource['emptyState']['actions'][0])->toMatchArray([
            'label' => 'Create topic',
            'variant' => 'info',
            'icon' => 'Plus',
            'buttonClass' => 'create-topic',
            'dataAttributes' => ['data-intent' => 'create'],
            'meta' => ['source' => 'empty-state'],
        ])
        ->and($resource['emptyState']['actions'][0]['url'])->toMatchArray([
            'url' => '/topics/create',
            'newTab' => true,
        ]);
});

it('keeps filtered no-results distinct from the genuine empty state', function () {
    EmptyStateTopic::query()->create([
        'name' => 'Laravel',
        'status' => 'published',
    ]);

    $resource = (new EmptyStateTopicsTable)->resolve(
        emptyStateRequest(['search' => 'missing']),
    )->toArray();

    expect($resource['results']['total'])->toBe(0)
        ->and($resource['emptyState'])->toBeNull();
});

it('renders normalized per-row data attributes from model and transformed data', function () {
    EmptyStateTopic::query()->create([
        'name' => 'Laravel',
        'status' => 'published',
    ]);

    $row = (new EmptyStateTopicsTable)->resolve(emptyStateRequest())
        ->toArray()['results']['data'][0];

    expect($row['_table']['dataAttributes'])->toBe([
        'data-record-id' => 1,
        'data-status' => 'PUBLISHED',
    ]);
});

it('rejects row attributes that would overwrite package-owned row state', function () {
    EmptyStateTopic::query()->create([
        'name' => 'Laravel',
        'status' => 'published',
    ]);

    expect(fn () => (new InvalidRowAttributesTable)->resolve(emptyStateRequest()))
        ->toThrow(LogicException::class, 'Invalid table data attribute [selected]');
});

it('translates the default empty-state title', function () {
    app()->setLocale('vi');

    expect(EmptyState::make(icon: false)->toArray())
        ->title->toBe('Không tìm thấy kết quả.')
        ->icon->toBeFalse();

    app()->setLocale('en');
});
