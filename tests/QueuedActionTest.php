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
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Actions\QueuedActionRepository;
use Musing\InertiaTable\Actions\QueuedActionSnapshot;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Contracts\ActionContext;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Jobs\ExecuteQueuedAction;
use Musing\InertiaTable\Selection;
use Musing\InertiaTable\Table;

class QueuedActionUser extends Authenticatable
{
    protected $table = 'queued_action_users';

    protected $guarded = [];

    public $timestamps = false;
}

class QueuedActionRecord extends Model
{
    protected $table = 'queued_action_topics';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'featured' => 'boolean',
            'archived' => 'boolean',
        ];
    }
}

class QueuedActionUuidRecord extends Model
{
    protected $table = 'queued_action_uuid_topics';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}

class QueuedActionFollowUp implements ShouldQueue
{
    use Queueable;
}

class QueuedActionMiddleware
{
    public function handle(object $job, Closure $next): mixed
    {
        return $next($job);
    }
}

class QueuedActionTestContext implements ActionContext
{
    /** @var array<int, array{int|string|null, array<string, mixed>}> */
    public static array $restored = [];

    private ?AuthenticatableContract $previousUser = null;

    private string $previousTenant = '';

    public function actorId(Request $request, Table $table, Action $action): int|string|null
    {
        return $request->user()?->getAuthIdentifier();
    }

    public function restore(int|string|null $actorId, array $attributes): void
    {
        $this->previousUser = Auth::user();
        $this->previousTenant = QueuedActionTopicsTable::$tenant;
        Auth::guard()->setUser(QueuedActionUser::query()->find($actorId));
        QueuedActionTopicsTable::$tenant = (string) ($attributes['tenant'] ?? '');
        self::$restored[] = [$actorId, $attributes];
    }

    public function release(): void
    {
        QueuedActionTopicsTable::$tenant = $this->previousTenant;

        if ($this->previousUser instanceof AuthenticatableContract) {
            Auth::guard()->setUser($this->previousUser);
        } else {
            Auth::guard()->forgetUser();
        }
    }
}

class QueuedActionTopicsTable extends Table
{
    public static string $tenant = 'tenant-one';

    public static bool $removeAction = false;

    public static bool $changeAction = false;

    public static bool $denyAction = false;

    /** @var array<int, array<int, mixed>> */
    public static array $events = [];

    protected ?string $name = 'queued_action_topics';

    public function query(): Builder
    {
        return QueuedActionRecord::query()->where('tenant', self::$tenant);
    }

    public function columns(): array
    {
        return [TextColumn::make('name')->searchable()];
    }

    public function filters(): array
    {
        return [BooleanFilter::make('active')];
    }

    public function actions(): array
    {
        if (self::$removeAction) {
            return [];
        }

        $feature = Action::make('queue-feature', 'Feature selected')
            ->bulk()
            ->authorize(fn () => ! self::$denyAction && Auth::user()?->name === 'Allowed')
            ->handle(fn (QueuedActionRecord $topic) => $topic->update(['featured' => true]))
            ->chunkSize(self::$changeAction ? 2 : 1)
            ->queue(
                connection: 'database',
                queue: 'table-actions',
                delay: 3,
                expiresAfter: 3600,
            )
            ->scopeAttributes(fn () => ['tenant' => self::$tenant])
            ->context(QueuedActionTestContext::class)
            ->middleware([new QueuedActionMiddleware])
            ->tags(['tenant:'.self::$tenant])
            ->chain([new QueuedActionFollowUp])
            ->redirectAfterDispatch('/operations')
            ->onCompleted(fn (QueuedActionSnapshot $snapshot, mixed $result) => self::$events[] = [
                'completed',
                $snapshot->id,
                $result,
            ])
            ->onFailure(fn (QueuedActionSnapshot $snapshot, Throwable $exception) => self::$events[] = [
                'failed',
                $snapshot->id,
                $exception->getMessage(),
            ]);

        return [
            $feature,
            Action::make('queue-selection', 'Archive matching')
                ->bulk()
                ->handleSelection(fn (Selection $selection) => $selection->query()->update(['archived' => true]))
                ->queue(expiresAfter: 3600)
                ->scopeAttributes(fn () => ['tenant' => self::$tenant])
                ->context(QueuedActionTestContext::class),
            Action::make('queue-available', 'Archive available')
                ->bulk()
                ->disabled(fn (QueuedActionRecord $topic) => $topic->getKey() === 2)
                ->handle(fn (QueuedActionRecord $topic) => $topic->update(['archived' => true]))
                ->chunkSize(2)
                ->queue(expiresAfter: 3600)
                ->scopeAttributes(fn () => ['tenant' => self::$tenant])
                ->context(QueuedActionTestContext::class),
            Action::make('queue-failure', 'Fail safely')
                ->bulk()
                ->handle(fn () => throw new RuntimeException('Private database detail.'))
                ->queue(expiresAfter: 3600)
                ->failureMessage('The action could not be completed.')
                ->scopeAttributes(fn () => ['tenant' => self::$tenant])
                ->context(QueuedActionTestContext::class)
                ->onFailure(fn (QueuedActionSnapshot $snapshot, Throwable $exception) => self::$events[] = [
                    'failed',
                    $snapshot->id,
                    $exception->getMessage(),
                ]),
            Action::make('queue-result', 'Build result')
                ->bulk()
                ->handleSelection(fn (Selection $selection) => ['count' => $selection->count()])
                ->queue(expiresAfter: 3600)
                ->scopeAttributes(fn () => ['tenant' => self::$tenant])
                ->context(QueuedActionTestContext::class),
            Action::make('queue-completion-redirect', 'Redirect when complete')
                ->bulk()
                ->handleSelection(fn () => null)
                ->after('/actions/completed')
                ->queue(expiresAfter: 3600)
                ->scopeAttributes(fn () => ['tenant' => self::$tenant])
                ->context(QueuedActionTestContext::class),
        ];
    }
}

class QueuedActionUuidTopicsTable extends Table
{
    protected ?string $name = 'queued_action_uuid_topics';

    public function query(): Builder
    {
        return QueuedActionUuidRecord::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name')];
    }

    public function actions(): array
    {
        return [
            Action::make('queue-feature-uuid')
                ->bulk()
                ->handle(fn (QueuedActionUuidRecord $topic) => $topic->update(['featured' => true]))
                ->queue(expiresAfter: 3600)
                ->context(QueuedActionTestContext::class),
        ];
    }
}

beforeEach(function () {
    Cache::flush();
    Queue::fake();
    QueuedActionTopicsTable::$tenant = 'tenant-one';
    QueuedActionTopicsTable::$removeAction = false;
    QueuedActionTopicsTable::$changeAction = false;
    QueuedActionTopicsTable::$denyAction = false;
    QueuedActionTopicsTable::$events = [];
    QueuedActionTestContext::$restored = [];

    Schema::create('queued_action_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('queued_action_topics', function (Blueprint $table) {
        $table->id();
        $table->string('tenant');
        $table->string('name');
        $table->boolean('active');
        $table->boolean('featured')->default(false);
        $table->boolean('archived')->default(false);
    });
    Schema::create('queued_action_uuid_topics', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->boolean('featured')->default(false);
    });
    QueuedActionRecord::query()->insert([
        ['tenant' => 'tenant-one', 'name' => 'Alpha', 'active' => false],
        ['tenant' => 'tenant-one', 'name' => 'Beta', 'active' => true],
        ['tenant' => 'tenant-one', 'name' => 'Gamma', 'active' => true],
        ['tenant' => 'tenant-two', 'name' => 'Other', 'active' => true],
    ]);
    QueuedActionUuidRecord::query()->insert([
        ['id' => 'topic-alpha', 'name' => 'Alpha', 'featured' => false],
        ['id' => 'topic-beta', 'name' => 'Beta', 'featured' => false],
        ['id' => 'topic-gamma', 'name' => 'Gamma', 'featured' => false],
    ]);
});

function queuedActionEndpoint(string $key): string
{
    return collect((new QueuedActionTopicsTable)->resolve()->toArray()['actions'])
        ->firstWhere('key', $key)['endpoint']['url'];
}

function queuedActionUuidEndpoint(): string
{
    return collect((new QueuedActionUuidTopicsTable)->resolve()->toArray()['actions'])
        ->firstWhere('key', 'queue-feature-uuid')['endpoint']['url'];
}

/** @param array<string, mixed> $overrides */
function queuedActionPayload(array $overrides = []): array
{
    return [
        'idempotencyKey' => 'request-one',
        'ids' => [1, 2, 3],
        ...$overrides,
    ];
}

function capturedQueuedActionJob(string $action = 'queue-feature'): ExecuteQueuedAction
{
    $captured = null;
    Queue::assertPushed(ExecuteQueuedAction::class, function (ExecuteQueuedAction $job) use (&$captured, $action) {
        if ($job->snapshot->actionKey !== $action) {
            return false;
        }

        $captured = $job;

        return true;
    });

    expect($captured)->toBeInstanceOf(ExecuteQueuedAction::class);

    return $captured;
}

it('dispatches a closure-free immutable action snapshot with queue customization', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);

    $definition = collect((new QueuedActionTopicsTable)->resolve()->toArray()['actions'])
        ->firstWhere('key', 'queue-feature');
    expect($definition['queued'])->toBeTrue();

    $response = $this->postJson(queuedActionEndpoint('queue-feature'), queuedActionPayload())
        ->assertStatus(202)
        ->assertJsonPath('action.status', 'queued')
        ->assertJsonPath('action.total', 3)
        ->assertJsonPath('action.redirect', '/operations')
        ->assertJsonPath('action.duplicate', false);
    $job = capturedQueuedActionJob();

    expect($job->snapshot)
        ->tableClass->toBe(QueuedActionTopicsTable::class)
        ->tableName->toBe('queued_action_topics')
        ->actionKey->toBe('queue-feature')
        ->actorId->toBe($user->getKey())
        ->scopeAttributes->toBe(['tenant' => 'tenant-one'])
        ->locale->toBe(app()->getLocale())
        ->and($job->snapshot->selection)->toMatchArray([
            'all' => false,
            'keys' => [1, 2, 3],
            'table' => 'queued_action_topics',
        ])
        ->and($job->connection)->toBe('database')
        ->and($job->queue)->toBe('table-actions')
        ->and($job->delay)->toBe(3)
        ->and($job->afterCommit)->toBeTrue()
        ->and($job->middleware)->toHaveCount(1)
        ->and($job->chained)->toHaveCount(1)
        ->and($job->tags())->toContain('tenant:tenant-one')
        ->and(serialize($job))->not->toContain('Closure')
        ->and($response->json('action.id'))->toBe($job->snapshot->id)
        ->and($response->json('action.statusEndpoint'))->toContain('signature=')
        ->and($response->json('action'))->not->toHaveKey('_accessHash');
});

it('preserves explicit UUID keys through dispatch and worker execution', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedActionUuidEndpoint(), [
        'idempotencyKey' => 'uuid-request',
        'ids' => ['topic-alpha', 'topic-gamma'],
    ])->assertStatus(202);
    $job = capturedQueuedActionJob('queue-feature-uuid');
    $job->handle(app(QueuedActionRepository::class));

    expect($job->snapshot->selection['keys'])->toBe(['topic-alpha', 'topic-gamma'])
        ->and(QueuedActionUuidRecord::query()->where('featured', true)->orderBy('id')->pluck('id')->all())
        ->toBe(['topic-alpha', 'topic-gamma']);
});

it('restores context and reports chunk progress for per-model handlers', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $dispatch = $this->postJson(queuedActionEndpoint('queue-feature'), queuedActionPayload())
        ->assertStatus(202);
    $job = capturedQueuedActionJob();

    Auth::guard()->forgetUser();
    QueuedActionTopicsTable::$tenant = 'tenant-two';
    $job->handle(app(QueuedActionRepository::class));
    $job->handle(app(QueuedActionRepository::class));

    expect(QueuedActionRecord::query()->where('tenant', 'tenant-one')->where('featured', true)->count())->toBe(3)
        ->and(QueuedActionTestContext::$restored)->toBe([
            [$user->getKey(), ['tenant' => 'tenant-one']],
        ])
        ->and(Auth::user())->toBeNull()
        ->and(QueuedActionTopicsTable::$tenant)->toBe('tenant-two')
        ->and(app(QueuedActionRepository::class)->get($job->snapshot->id))
        ->status->toBe('completed')
        ->processed->toBe(3)
        ->succeeded->toBe(3)
        ->skipped->toBe(0)
        ->and(QueuedActionTopicsTable::$events[0][0])->toBe('completed');
    expect(QueuedActionTopicsTable::$events)->toHaveCount(1)
        ->and(QueuedActionTestContext::$restored)->toHaveCount(1);

    QueuedActionTopicsTable::$tenant = 'tenant-one';
    $this->actingAs($user)->getJson($dispatch->json('action.statusEndpoint'))
        ->assertOk()
        ->assertJsonPath('action.status', 'completed')
        ->assertJsonPath('action.processed', 3)
        ->assertJsonMissingPath('action._accessHash');
});

it('executes the captured all-matching selection with filters and exclusions', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedActionEndpoint('queue-selection'), queuedActionPayload([
        'ids' => [],
        'selection' => [
            'all' => true,
            'keys' => [],
            'except' => [2],
            'table' => 'queued_action_topics',
            'state' => [
                'search' => '',
                'sort' => 'name',
                'filters' => [
                    'active' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
                ],
            ],
        ],
    ]))->assertStatus(202);
    $job = capturedQueuedActionJob('queue-selection');
    $job->handle(app(QueuedActionRepository::class));

    expect(QueuedActionRecord::query()->where('archived', true)->pluck('id')->all())->toBe([3])
        ->and(app(QueuedActionRepository::class)->get($job->snapshot->id))
        ->status->toBe('completed')
        ->processed->toBeNull()
        ->succeeded->toBeNull();
});

it('counts models that become unavailable as skipped without loading the full selection', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedActionEndpoint('queue-available'), queuedActionPayload())->assertStatus(202);
    $job = capturedQueuedActionJob('queue-available');
    $job->handle(app(QueuedActionRepository::class));

    expect(QueuedActionRecord::query()->where('archived', true)->pluck('id')->all())->toBe([1, 3])
        ->and(app(QueuedActionRepository::class)->get($job->snapshot->id))
        ->processed->toBe(3)
        ->succeeded->toBe(2)
        ->skipped->toBe(1);
});

it('deduplicates repeated requests for the same captured action and selection', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $endpoint = queuedActionEndpoint('queue-feature');
    $first = $this->postJson($endpoint, queuedActionPayload())->assertStatus(202);
    Cache::forget('inertia-table:queued-action:status:'.$first->json('action.id'));
    $second = $this->postJson($endpoint, queuedActionPayload())->assertStatus(202);

    expect($second->json('action.id'))->toBe($first->json('action.id'))
        ->and($second->json('action.duplicate'))->toBeTrue()
        ->and($second->json('action.total'))->toBe(3)
        ->and($second->json('action.statusEndpoint'))->toContain('signature=');
    $this->getJson($second->json('action.statusEndpoint'))
        ->assertOk()
        ->assertJsonPath('action.status', 'queued');
    Queue::assertPushed(ExecuteQueuedAction::class, 1);
});

it('requires an idempotency key before accepting a queued operation', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user)
        ->postJson(queuedActionEndpoint('queue-feature'), ['ids' => [1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('idempotencyKey');

    Queue::assertNotPushed(ExecuteQueuedAction::class);
});

it('rechecks definitions and authorization before processing the snapshot', function (string $change) {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedActionEndpoint('queue-feature'), queuedActionPayload())->assertStatus(202);
    $job = capturedQueuedActionJob();
    QueuedActionTopicsTable::${$change} = true;

    expect(fn () => $job->handle(app(QueuedActionRepository::class)))
        ->toThrow(LogicException::class);

    expect(QueuedActionRecord::query()->where('featured', true)->count())->toBe(0);
})->with([
    'changed definition' => 'changeAction',
    'removed definition' => 'removeAction',
    'revoked authorization' => 'denyAction',
]);

it('records a safe public failure and preserves the original exception for the callback', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedActionEndpoint('queue-failure'), queuedActionPayload(['ids' => [1]]))
        ->assertStatus(202);
    $job = capturedQueuedActionJob('queue-failure');

    try {
        $job->handle(app(QueuedActionRepository::class));
        $this->fail('The queued action should fail.');
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    $job->handle(app(QueuedActionRepository::class));

    expect(app(QueuedActionRepository::class)->get($job->snapshot->id))
        ->status->toBe('failed')
        ->message->toBe('The action could not be completed.')
        ->message->not->toContain('Private database detail')
        ->and(QueuedActionTopicsTable::$events)->toBe([
            ['failed', $job->snapshot->id, 'Private database detail.'],
        ]);
});

it('publishes serializable result metadata and completion redirects', function (string $action, mixed $result, ?string $redirect) {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $this->postJson(queuedActionEndpoint($action), queuedActionPayload(['ids' => [1, 3]]))
        ->assertStatus(202);
    $job = capturedQueuedActionJob($action);
    $job->handle(app(QueuedActionRepository::class));

    expect(app(QueuedActionRepository::class)->get($job->snapshot->id))
        ->status->toBe('completed')
        ->result->toBe($result)
        ->redirect->toBe($redirect);
})->with([
    'result metadata' => ['queue-result', ['count' => 2], null],
    'completion redirect' => ['queue-completion-redirect', null, 'http://localhost/actions/completed'],
]);

it('expires status without exposing prior result metadata', function () {
    $repository = app(QueuedActionRepository::class);
    $repository->put('action-id', [
        'id' => 'action-id',
        'status' => 'completed',
        'expiresAt' => time() - 1,
        'result' => ['private' => true],
        'redirect' => '/private',
    ], 3600);

    expect($repository->get('action-id'))
        ->status->toBe('expired')
        ->result->toBeNull()
        ->redirect->toBeNull();
});

it('protects queued action status by signature actor and tenant scope', function () {
    $user = QueuedActionUser::query()->create(['name' => 'Allowed']);
    $this->actingAs($user);
    $dispatch = $this->postJson(queuedActionEndpoint('queue-feature'), queuedActionPayload())->assertStatus(202);
    $endpoint = $dispatch->json('action.statusEndpoint');

    $denied = QueuedActionUser::query()->create(['name' => 'Denied']);
    $this->actingAs($denied)->getJson($endpoint)->assertForbidden();

    QueuedActionTopicsTable::$tenant = 'tenant-two';
    $this->actingAs($user)->getJson($endpoint)->assertNotFound();

    QueuedActionTopicsTable::$tenant = 'tenant-one';
    $id = basename((string) parse_url($endpoint, PHP_URL_PATH));
    $tampered = str_replace($id, '00000000-0000-0000-0000-000000000000', $endpoint);
    $this->getJson($tampered)->assertForbidden();
});

it('allows queue configuration only on server-managed bulk actions', function () {
    expect(fn () => Action::make('invalid')->bulk()->queue())
        ->toThrow(LogicException::class)
        ->and(fn () => Action::make('invalid')->row()->handle(fn () => null)->queue())
        ->toThrow(LogicException::class)
        ->and(fn () => Action::make('invalid')->bulk()->endpoint('post', '/external')->queue())
        ->toThrow(LogicException::class)
        ->and(fn () => Action::make('invalid')->bulk()->handle(fn () => null)->queue(delay: -1))
        ->toThrow(LogicException::class)
        ->and(fn () => Action::make('invalid')->bulk()->handle(fn () => null)->queue()->row())
        ->toThrow(LogicException::class);

    config()->set('inertia-table.actions.queue', [
        'connection' => 'redis',
        'queue' => 'bulk',
        'delay' => 4,
        'expires_after' => 7200,
        'status_retention' => 1800,
        'after_commit' => false,
    ]);
    $configuration = Action::make('configured')
        ->bulk()
        ->handle(fn () => null)
        ->queue()
        ->queueConfiguration();

    expect($configuration)->toBe([
        'connection' => 'redis',
        'queue' => 'bulk',
        'delay' => 4,
        'expiresAfter' => 7200,
        'statusRetention' => 1800,
        'afterCommit' => false,
    ]);
});
