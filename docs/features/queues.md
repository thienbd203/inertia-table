# Queues

Managed bulk actions and exports can run outside the request. The dispatcher
stores a normalized snapshot, and a worker reconstructs the table and its query.
Live requests, builders, models, table instances, and definition closures are
never serialized.

## Queue a bulk action

Define the bulk handler before `queue()`:

```php
use Musing\InertiaTable\Actions\QueuedActionSnapshot;
use Throwable;

Action::make('archive', 'Archive')
    ->bulk()
    ->handle(fn (Topic $topic) => $topic->archive())
    ->chunkSize(500)
    ->queue(
        connection: 'redis',
        queue: 'table-actions',
        delay: 0,
        expiresAfter: 86_400,
        afterCommit: true,
    )
    ->scopeAttributes(fn () => ['tenant' => tenant()->id])
    ->tags(fn () => ['tenant:'.tenant()->id])
    ->onCompleted(
        fn (QueuedActionSnapshot $snapshot, mixed $result) => null,
    )
    ->onFailure(
        fn (QueuedActionSnapshot $snapshot, Throwable $exception) => null,
    );
```

Connection, queue, delay, expiry, retention, and after-commit behavior fall back
to `inertia-table.actions.queue`.

## Worker setup

Run a normal Laravel queue worker for the configured connection and queue:

```bash
php artisan queue:work redis --queue=table-actions,exports
```

Use a persistent cache shared by web and worker processes, such as Redis,
Memcached, database, or DynamoDB. The `array` cache store is process-local and
cannot provide shared idempotency, execution locks, or status polling.

Set `retry_after` or the queue visibility timeout longer than the longest action
attempt. Queue delivery remains at-least-once; handlers that perform external
side effects should be idempotent.

## Snapshot safety

A queued action snapshot contains:

- normalized explicit or all-matching selection;
- definition fingerprint;
- authenticated actor identity;
- locale;
- scalar application scope.

The worker restores context, reruns authorization, verifies the table and action
definition fingerprint, and rechecks row selectability and availability.
Definitions removed or materially changed after dispatch fail safely.

Use `context()` with an `ActionContext` implementation to restore tenant state.
Use `middleware()` for queue middleware, `chain()` for follow-up jobs,
`redirectAfterDispatch()` for an operations page, and `failureMessage()` for
safe user-facing copy.

## Idempotency and progress

Repeated submissions with the same idempotency key reuse one operation. Retrying
a terminal operation requires a new key.

Per-model handlers update `processed`, `succeeded`, and `skipped` after each
chunk. Set-based `handleSelection()` actions expose lifecycle state without
pretending to know row-level progress.

The Vue renderer emits `action-queued`, `action-progress`, then
`action-success` or `action-error`. It polls a signed, actor-scoped status URL.
Closing the dialog does not stop polling; it opens again on completion, failure,
or expiry.

Override `queuedAction`, or use `useActions().updateQueuedAction()`, when the
application delivers progress through notifications or a realtime channel.

## Queue an export

```php
Export::make('archive', 'Export archive')
    ->filtered()
    ->queue(
        connection: 'redis',
        queue: 'exports',
        disk: 's3',
        expiresAfter: 86_400,
    );
```

Export queue defaults live under `inertia-table.queue`. Generated files are
deleted after expiry, and partial files are removed after failure. Read
[exports](/features/exports) for delivery URLs and context restoration.

## Deploying queue changes

Deploy PHP producers and workers together. When a release changes snapshot
semantics, pause dispatches or drain pending work before older workers consume
new snapshots.
