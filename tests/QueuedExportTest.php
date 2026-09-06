<?php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\ExportManager;
use Musing\InertiaTable\Exports\QueuedExportDispatcher;
use Musing\InertiaTable\Exports\QueuedExportRepository;
use Musing\InertiaTable\Exports\QueuedExportSnapshot;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Jobs\CleanupQueuedExport;
use Musing\InertiaTable\Jobs\GenerateQueuedExport;
use Musing\InertiaTable\Table;

class QueuedExportUser extends Authenticatable
{
    protected $table = 'queued_export_users';

    protected $guarded = [];

    public $timestamps = false;
}

class QueuedExportRecord extends Model
{
    protected $table = 'queued_export_topics';

    protected $guarded = [];

    public $timestamps = false;
}

class QueuedExportFollowUp implements ShouldQueue
{
    use Queueable;
}

class QueuedExportTestContext implements ExportContext
{
    public static array $restored = [];

    private ?AuthenticatableContract $previousUser = null;

    private string $previousTenant = '';

    public function actorId(Request $request, Table $table, Export $export): int|string|null
    {
        return $request->user()?->getAuthIdentifier();
    }

    public function restore(int|string|null $actorId, array $attributes): void
    {
        $this->previousUser = Auth::user();
        $this->previousTenant = QueuedExportTopicsTable::$tenant;
        $actor = QueuedExportUser::query()->find($actorId);
        Auth::guard()->setUser($actor);
        QueuedExportTopicsTable::$tenant = (string) ($attributes['tenant'] ?? '');
        self::$restored[] = [$actorId, $attributes];
    }

    public function release(): void
    {
        QueuedExportTopicsTable::$tenant = $this->previousTenant;

        if ($this->previousUser instanceof AuthenticatableContract) {
            Auth::guard()->setUser($this->previousUser);
        } else {
            Auth::guard()->forgetUser();
        }
    }
}

class QueuedExportTopicsTable extends Table
{
    public static string $tenant = 'tenant-one';

    public static bool $removeQueuedExport = false;

    public static bool $changeQueuedExport = false;

    public static bool $throwWhenResolvingFilename = false;

    public static int $filenameResolutionCount = 0;

    /** @var array<int, array<int, mixed>> */
    public static array $events = [];

    /** @var array<int, string> */
    public static array $callbackLocales = [];

    protected ?string $name = 'queued_export_topics';

    public function query(): Builder
    {
        return QueuedExportRecord::query()->where('tenant', self::$tenant);
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID')->sortable()->summary('count'),
            TextColumn::make('name', 'Name')->searchable(),
            TextColumn::make('tenant', 'Tenant'),
        ];
    }

    public function filters(): array
    {
        return [BooleanFilter::make('active')];
    }

    public function exports(): array
    {
        $exports = [
            Export::make('sync', 'Sync', 'topics.csv')->filtered()->withSummaries(),
        ];

        if (self::$removeQueuedExport) {
            return $exports;
        }

        $queued = Export::make('queued', 'Queued', function () {
            self::$filenameResolutionCount++;

            if (self::$throwWhenResolvingFilename) {
                throw new RuntimeException('Filename resolver exposed implementation details.');
            }

            return 'topics.csv';
        })
            ->filtered()
            ->withSummaries()
            ->queue(
                connection: 'database',
                queue: 'exports',
                delay: 5,
                disk: 'queued-exports',
                expiresAfter: 3600,
            )
            ->scopeAttributes(fn () => ['tenant' => self::$tenant])
            ->context(QueuedExportTestContext::class)
            ->redirectAfterDispatch('/exports/history')
            ->deliveryUrlUsing(fn (QueuedExportSnapshot $snapshot) => "/downloads/{$snapshot->id}")
            ->chain([new QueuedExportFollowUp])
            ->onReady(function (QueuedExportSnapshot $snapshot, ?string $url) {
                self::$events[] = ['ready', $snapshot->id, $url];
                self::$callbackLocales[] = app()->getLocale();
            })
            ->onFailure(fn (QueuedExportSnapshot $snapshot, Throwable $exception) => self::$events[] = ['failed', $snapshot->id, $exception->getMessage()])
            ->authorize(fn () => Auth::user()?->name === 'Allowed');

        if (self::$changeQueuedExport) {
            $queued->allRows();
        }

        return [
            ...$exports,
            $queued,
            Export::make('queued-selected', 'Queued selected', 'selected.csv')
                ->selected()
                ->queue(disk: 'queued-exports', expiresAfter: 3600)
                ->scopeAttributes(fn () => ['tenant' => self::$tenant])
                ->context(QueuedExportTestContext::class),
        ];
    }
}

beforeEach(function () {
    Cache::flush();
    Queue::fake();
    Storage::fake('queued-exports');
    QueuedExportTopicsTable::$tenant = 'tenant-one';
    QueuedExportTopicsTable::$removeQueuedExport = false;
    QueuedExportTopicsTable::$changeQueuedExport = false;
    QueuedExportTopicsTable::$throwWhenResolvingFilename = false;
    QueuedExportTopicsTable::$filenameResolutionCount = 0;
    QueuedExportTopicsTable::$events = [];
    QueuedExportTopicsTable::$callbackLocales = [];
    QueuedExportTestContext::$restored = [];

    Schema::create('queued_export_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('queued_export_topics', function (Blueprint $table) {
        $table->id();
        $table->string('tenant');
        $table->string('name');
        $table->boolean('active');
    });
    QueuedExportRecord::query()->insert([
        ['tenant' => 'tenant-one', 'name' => 'Alpha', 'active' => false],
        ['tenant' => 'tenant-one', 'name' => 'Beta', 'active' => true],
        ['tenant' => 'tenant-one', 'name' => 'Gamma', 'active' => true],
        ['tenant' => 'tenant-two', 'name' => 'Other', 'active' => true],
    ]);
});

/** @return array<string, mixed> */
function queuedExportResource(): array
{
    return (new QueuedExportTopicsTable)->resolve()->toArray();
}

function queuedExportEndpoint(string $key): string
{
    return collect(queuedExportResource()['exports'])->firstWhere('key', $key)['endpoint'];
}

/** @param array<string, mixed> $overrides */
function queuedExportPayload(array $overrides = []): array
{
    return [
        'idempotencyKey' => 'request-one',
        'state' => [
            'sort' => '-id',
            'filters' => [
                'active' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
                'unknown' => ['enabled' => true, 'clause' => 'equals', 'value' => 'unsafe'],
            ],
        ],
        ...$overrides,
    ];
}

function capturedQueuedExportJob(): GenerateQueuedExport
{
    $captured = null;
    Queue::assertPushed(GenerateQueuedExport::class, function (GenerateQueuedExport $job) use (&$captured) {
        $captured = $job;

        return true;
    });

    expect($captured)->toBeInstanceOf(GenerateQueuedExport::class);

    return $captured;
}

it('dispatches a closure-free normalized snapshot with queue configuration and chain', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);

    $response = $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())
        ->assertStatus(202)
        ->assertJsonPath('export.status', 'dispatched')
        ->assertJsonPath('export.redirect', '/exports/history')
        ->assertJsonPath('export.duplicate', false);
    $job = capturedQueuedExportJob();

    expect($job->snapshot)
        ->tableClass->toBe(QueuedExportTopicsTable::class)
        ->exportKey->toBe('queued')
        ->actorId->toBe($user->getKey())
        ->scopeAttributes->toBe(['tenant' => 'tenant-one'])
        ->disk->toBe('queued-exports')
        ->and($job->snapshot->state['filters'])->not->toHaveKey('unknown')
        ->and($job->connection)->toBe('database')
        ->and($job->queue)->toBe('exports')
        ->and($job->delay)->toBe(5)
        ->and($job->chained)->toHaveCount(1)
        ->and(serialize($job))->not->toContain('Closure')
        ->and($response->json('export.id'))->toBe($job->snapshot->id)
        ->and($response->json('export.statusEndpoint'))->toContain('signature=')
        ->and($response->json('export'))->not->toHaveKey('_accessHash');
});

it('restores actor and tenant, writes the same filtered CSV with summaries, and publishes ready status', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $payload = queuedExportPayload();
    $sync = $this->post(queuedExportEndpoint('sync'), $payload)->assertOk()->streamedContent();
    $dispatch = $this->postJson(queuedExportEndpoint('queued'), $payload)->assertStatus(202);
    $job = capturedQueuedExportJob();

    Auth::guard()->forgetUser();
    QueuedExportTopicsTable::$tenant = 'tenant-two';
    $job->handle(app(ExportManager::class), app(QueuedExportRepository::class));

    Storage::disk('queued-exports')->assertExists($job->snapshot->path);
    expect(Storage::disk('queued-exports')->get($job->snapshot->path))->toBe($sync)
        ->and($sync)->toEndWith("2,,\n")
        ->and(QueuedExportTestContext::$restored)->toBe([
            [$user->getKey(), ['tenant' => 'tenant-one']],
        ])
        ->and(Auth::user())->toBeNull()
        ->and(QueuedExportTopicsTable::$tenant)->toBe('tenant-two')
        ->and(app(QueuedExportRepository::class)->get($job->snapshot->id))
        ->status->toBe('ready')
        ->url->toBe('/downloads/'.$job->snapshot->id)
        ->and(QueuedExportTopicsTable::$events[0])->toBe([
            'ready',
            $job->snapshot->id,
            '/downloads/'.$job->snapshot->id,
        ]);
    Queue::assertPushed(CleanupQueuedExport::class);

    QueuedExportTopicsTable::$tenant = 'tenant-one';
    $this->actingAs($user)->getJson($dispatch->json('export.statusEndpoint'))
        ->assertOk()
        ->assertJsonPath('export.status', 'ready')
        ->assertJsonPath('export.url', '/downloads/'.$job->snapshot->id)
        ->assertJsonMissingPath('export._accessHash');

    QueuedExportTopicsTable::$tenant = 'tenant-two';
    $this->getJson($dispatch->json('export.statusEndpoint'))->assertNotFound();
});

it('restores the dispatch locale while generating an export and calling its callbacks', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    app()->setLocale('vi');
    $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())->assertStatus(202);
    $job = capturedQueuedExportJob();

    app()->setLocale('en');
    $job->handle(app(ExportManager::class), app(QueuedExportRepository::class));

    expect($job->snapshot->locale)->toBe('vi')
        ->and(QueuedExportTopicsTable::$callbackLocales)->toBe(['vi'])
        ->and(app()->getLocale())->toBe('en');
});

it('protects queued status endpoints with the export authorization and signature', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $dispatch = $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())->assertStatus(202);
    $statusEndpoint = $dispatch->json('export.statusEndpoint');

    $denied = QueuedExportUser::query()->create(['name' => 'Denied']);
    $this->actingAs($denied)->getJson($statusEndpoint)->assertForbidden();

    $statusId = basename((string) parse_url($statusEndpoint, PHP_URL_PATH));
    $tampered = str_replace($statusId, '00000000-0000-0000-0000-000000000000', $statusEndpoint);
    $this->actingAs($user)->getJson($tampered)->assertForbidden();
});

it('normalizes and executes all-matching selected snapshots with exclusions', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedExportEndpoint('queued-selected'), queuedExportPayload([
        'selection' => [
            'all' => true,
            'keys' => [],
            'except' => [2],
            'table' => 'queued_export_topics',
            'state' => [
                'sort' => 'id',
                'search' => '',
                'filters' => [
                    'active' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
                ],
            ],
        ],
    ]))->assertStatus(202);
    $job = capturedQueuedExportJob();
    $job->handle(app(ExportManager::class), app(QueuedExportRepository::class));
    $content = Storage::disk('queued-exports')->get($job->snapshot->path);

    expect($job->snapshot->selection)
        ->all->toBeTrue()
        ->except->toBe([2])
        ->and($content)->toContain('3,Gamma,tenant-one')
        ->and($content)->not->toContain('2,Beta,tenant-one');
});

it('deduplicates repeated submissions before a second job is created', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $endpoint = queuedExportEndpoint('queued');
    $first = $this->postJson($endpoint, queuedExportPayload())->assertStatus(202);
    $second = $this->postJson($endpoint, queuedExportPayload())->assertStatus(202);

    expect($second->json('export.id'))->toBe($first->json('export.id'))
        ->and($second->json('export.duplicate'))->toBeTrue();
    Queue::assertPushed(GenerateQueuedExport::class, 1);
});

it('keeps a terminal safe failure after a filename resolver fails following reservation', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    QueuedExportTopicsTable::$throwWhenResolvingFilename = true;
    $request = Request::create('/', 'POST');
    $request->setUserResolver(fn () => $user);
    $table = app(QueuedExportTopicsTable::class);
    $export = $table->export('queued');
    $state = queuedExportPayload()['state'];

    expect(fn () => app(QueuedExportDispatcher::class)->dispatch(
        $request,
        $table,
        $export,
        $state,
        null,
        'request-one',
    ))
        ->toThrow(RuntimeException::class, 'Filename resolver exposed implementation details.');
    Queue::assertNothingPushed();

    QueuedExportTopicsTable::$throwWhenResolvingFilename = false;
    $retry = app(QueuedExportDispatcher::class)->dispatch(
        $request,
        $table,
        $export,
        $state,
        null,
        'request-one',
    );

    expect($retry)
        ->status->toBe('failed')
        ->message->toBe(Export::DEFAULT_FAILURE_MESSAGE)
        ->duplicate->toBeTrue()
        ->and($retry['message'])
        ->not->toContain('Filename resolver exposed implementation details.')
        ->and(QueuedExportTopicsTable::$filenameResolutionCount)->toBe(1);
});

it('fails safely when the queued definition changes or is removed', function (string $mode) {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())->assertStatus(202);
    $job = capturedQueuedExportJob();
    Storage::disk('queued-exports')->put($job->snapshot->path, 'partial');
    QueuedExportTopicsTable::${$mode} = true;

    try {
        $job->handle(app(ExportManager::class), app(QueuedExportRepository::class));
        $this->fail('The changed queued export should fail.');
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    Storage::disk('queued-exports')->assertMissing($job->snapshot->path);
    expect(app(QueuedExportRepository::class)->get($job->snapshot->id))
        ->status->toBe('failed')
        ->url->toBeNull();
})->with([
    'changed' => 'changeQueuedExport',
    'removed' => 'removeQueuedExport',
]);

it('cleans partial files, restores context, and invokes the failure hook', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())->assertStatus(202);
    $job = capturedQueuedExportJob();
    Storage::disk('queued-exports')->put($job->snapshot->path, 'partial');
    Auth::guard()->forgetUser();
    QueuedExportTopicsTable::$tenant = 'tenant-two';

    $job->failed(new RuntimeException('Generation stopped.'));

    Storage::disk('queued-exports')->assertMissing($job->snapshot->path);
    expect(app(QueuedExportRepository::class)->get($job->snapshot->id))
        ->status->toBe('failed')
        ->message->toBe(Export::DEFAULT_FAILURE_MESSAGE)
        ->and(QueuedExportTopicsTable::$events)->toBe([
            ['failed', $job->snapshot->id, 'Generation stopped.'],
        ])
        ->and(Auth::user())->toBeNull()
        ->and(QueuedExportTopicsTable::$tenant)->toBe('tenant-two');
});

it('cleans up expired files and marks the status expired', function () {
    $repository = app(QueuedExportRepository::class);
    $repository->put('export-id', [
        'id' => 'export-id',
        'status' => 'ready',
        'url' => '/download',
    ], 3600);
    Storage::disk('queued-exports')->put('exports/file.csv', 'csv');

    (new CleanupQueuedExport('export-id', 'queued-exports', 'exports/file.csv'))->handle($repository);

    Storage::disk('queued-exports')->assertMissing('exports/file.csv');
    expect($repository->get('export-id'))
        ->status->toBe('expired')
        ->url->toBeNull();
});

it('invalidates an expired queued export status without refreshing its delivery data', function () {
    $repository = app(QueuedExportRepository::class);
    $repository->put('expired-export', [
        'id' => 'expired-export',
        'status' => 'ready',
        'url' => '/download',
        'redirect' => '/exports/history',
        'message' => 'stale',
        'expiresAt' => time() - 1,
    ], 3600);

    expect($repository->get('expired-export'))
        ->status->toBe('expired')
        ->url->toBeNull()
        ->redirect->toBeNull()
        ->message->toBeNull();
});

it('does not let a late failure delete or overwrite a ready export', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())->assertStatus(202);
    $job = capturedQueuedExportJob();
    $job->handle(app(ExportManager::class), app(QueuedExportRepository::class));

    $job->failed(new RuntimeException('Late worker failure.'));

    Storage::disk('queued-exports')->assertExists($job->snapshot->path);
    expect(app(QueuedExportRepository::class)->get($job->snapshot->id))
        ->status->toBe('ready');
});

it('does not generate an export while another worker owns its execution lock', function () {
    $user = QueuedExportUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedExportEndpoint('queued'), queuedExportPayload())->assertStatus(202);
    $job = capturedQueuedExportJob();
    $repository = app(QueuedExportRepository::class);
    $lock = $repository->executionLock($job->snapshot->id, 120);

    expect($lock->get())->toBeTrue();

    try {
        $job->handle(app(ExportManager::class), $repository);
    } finally {
        $lock->release();
    }

    Storage::disk('queued-exports')->assertMissing($job->snapshot->path);
    expect($repository->get($job->snapshot->id))->status->toBe('dispatched');
});
