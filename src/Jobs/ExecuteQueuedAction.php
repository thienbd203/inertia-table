<?php

namespace Musing\InertiaTable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use LogicException;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Actions\QueuedActionRepository;
use Musing\InertiaTable\Actions\QueuedActionSnapshot;
use Musing\InertiaTable\Contracts\ActionContext;
use Musing\InertiaTable\Selection;
use Musing\InertiaTable\Table;
use Throwable;

final class ExecuteQueuedAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param array<int, string> $actionTags */
    public function __construct(
        public readonly QueuedActionSnapshot $snapshot,
        public readonly int $statusRetention = 86400,
        public readonly array $actionTags = [],
    ) {}

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'inertia-table',
            'table:'.$this->snapshot->tableName,
            'action:'.$this->snapshot->actionKey,
            'operation:'.$this->snapshot->id,
            ...$this->actionTags,
        ];
    }

    public function handle(QueuedActionRepository $repository): void
    {
        $lock = $repository->executionLock(
            $this->snapshot->id,
            $this->executionLockSeconds(),
        );

        if (! $lock->get()) {
            $status = $this->status($repository);

            if (! in_array($status['status'] ?? null, ['completed', 'failed', 'expired'], true)) {
                $this->release(5);
            }

            return;
        }

        try {
            $this->execute($repository);
        } finally {
            $lock->release();
        }
    }

    private function execute(QueuedActionRepository $repository): void
    {
        $ttl = max($this->snapshot->expiresAt - time() + $this->statusRetention, $this->statusRetention);
        $status = $this->status($repository);

        if (in_array($status['status'] ?? null, ['completed', 'failed', 'expired'], true)) {
            return;
        }

        if ($this->snapshot->expiresAt <= time()) {
            $repository->put($this->snapshot->id, [
                ...$status,
                'status' => 'expired',
                'result' => null,
                'redirect' => null,
            ], $this->statusRetention);

            return;
        }

        $repository->put($this->snapshot->id, [
            ...$status,
            'status' => 'processing',
        ], $ttl);
        $context = app($this->snapshot->contextClass);

        if (! $context instanceof ActionContext) {
            throw new LogicException('The queued action context is invalid.');
        }

        $previousLocale = App::getLocale();
        $previousRequest = app()->bound('request') ? app('request') : null;
        $requestBound = false;
        try {
            $context->restore($this->snapshot->actorId, $this->snapshot->scopeAttributes);
            App::setLocale($this->snapshot->locale);
            [$request, $action, $selection] = $this->resolveDefinition();
            app()->instance('request', $request);
            $requestBound = true;
            $processed = 0;
            $succeeded = 0;
            $skipped = 0;
            $result = $action->execute(
                $selection,
                skipUnavailableModels: true,
                request: $request,
                onProgress: function (int $nextProcessed, int $nextSucceeded, int $nextSkipped) use (
                    $repository,
                    $ttl,
                    &$processed,
                    &$skipped,
                    &$succeeded,
                ): void {
                    $processed = $nextProcessed;
                    $succeeded = $nextSucceeded;
                    $skipped = $nextSkipped;
                    $repository->put($this->snapshot->id, [
                        ...$this->status($repository),
                        'status' => 'processing',
                        'processed' => $processed,
                        'succeeded' => $succeeded,
                        'skipped' => $skipped,
                    ], $ttl);
                },
            );
            [$normalizedResult, $redirect] = $this->normalizeResult($result);
            $repository->put($this->snapshot->id, [
                ...$this->status($repository),
                'status' => 'completed',
                'processed' => $action->handlesSelection() ? null : $processed,
                'succeeded' => $action->handlesSelection() ? null : $succeeded,
                'skipped' => $action->handlesSelection() ? null : $skipped,
                'result' => $normalizedResult,
                'redirect' => $redirect,
                'completedAt' => time(),
            ], $ttl);
            $action->notifyCompleted($this->snapshot, $result);
        } finally {
            App::setLocale($previousLocale);

            if ($requestBound) {
                if ($previousRequest instanceof Request) {
                    app()->instance('request', $previousRequest);
                } else {
                    app()->forgetInstance('request');
                }
            }

            $context->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $exception ??= new LogicException('The queued action failed.');
        $repository = app(QueuedActionRepository::class);
        $status = $this->status($repository);

        if (($status['status'] ?? null) === 'expired') {
            return;
        }

        $message = 'The queued action failed.';
        $previousLocale = App::getLocale();
        $previousRequest = app()->bound('request') ? app('request') : null;
        $requestBound = false;

        try {
            $context = app($this->snapshot->contextClass);

            if (! $context instanceof ActionContext) {
                throw new LogicException('The queued action context is invalid.');
            }

            try {
                $context->restore($this->snapshot->actorId, $this->snapshot->scopeAttributes);
                App::setLocale($this->snapshot->locale);
                [$request, $action] = $this->resolveDefinition();
                app()->instance('request', $request);
                $requestBound = true;
                $message = $action->publicFailureMessage();
                $action->notifyFailure($this->snapshot, $exception);
            } finally {
                App::setLocale($previousLocale);

                if ($requestBound) {
                    if ($previousRequest instanceof Request) {
                        app()->instance('request', $previousRequest);
                    } else {
                        app()->forgetInstance('request');
                    }
                }

                $context->release();
            }
        } catch (Throwable) {
            // Laravel keeps the original exception as the authoritative job failure.
        }

        $repository->put($this->snapshot->id, [
            ...$status,
            'status' => 'failed',
            'result' => null,
            'redirect' => null,
            'message' => $message,
            'failedAt' => time(),
        ], max($this->statusRetention, 1));
    }

    /** @return array{Request, Action, Selection} */
    private function resolveDefinition(): array
    {
        $table = app($this->snapshot->tableClass);

        if (! $table instanceof Table || $table->name() !== $this->snapshot->tableName) {
            throw new LogicException('The queued action table no longer exists or changed its identity.');
        }

        $action = $table->action($this->snapshot->actionKey);

        if (! $action instanceof Action || ! $action->hasHandler() || ! $action->isQueued()) {
            throw new LogicException('The queued action definition no longer exists.');
        }

        if (! hash_equals($this->snapshot->definitionFingerprint, $action->definitionFingerprint())) {
            throw new LogicException('The queued action definition changed after dispatch.');
        }

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => auth()->user());
        $resolved = $action->resolve(request: $request);

        if (! $resolved['authorized'] || $resolved['hidden'] || $resolved['disabled']) {
            throw new LogicException('The queued action is no longer authorized or available.');
        }

        return [$request, $action, $table->selection($this->snapshot->selection)];
    }

    /** @return array{mixed, string|null} */
    private function normalizeResult(mixed $result): array
    {
        if ($result instanceof RedirectResponse) {
            return [null, $result->getTargetUrl()];
        }

        if ($result instanceof Arrayable) {
            $result = $result->toArray();
        }

        if (is_array($result) || is_scalar($result) || $result === null) {
            try {
                json_encode($result, JSON_THROW_ON_ERROR);

                return [$result, null];
            } catch (Throwable) {
                return [null, null];
            }
        }

        return [null, null];
    }

    /** @return array<string, mixed> */
    private function status(QueuedActionRepository $repository): array
    {
        return $repository->get($this->snapshot->id) ?? [
            'id' => $this->snapshot->id,
            'action' => $this->snapshot->actionKey,
            'status' => 'queued',
            'expiresAt' => $this->snapshot->expiresAt,
        ];
    }

    private function executionLockSeconds(): int
    {
        $connection = is_string($this->connection) && $this->connection !== ''
            ? $this->connection
            : config('queue.default');
        $retryAfter = is_string($connection)
            ? config("queue.connections.{$connection}.retry_after")
            : null;

        return max((int) $retryAfter + 60, 120);
    }
}
