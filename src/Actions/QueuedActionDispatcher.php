<?php

namespace Musing\InertiaTable\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;
use Musing\InertiaTable\Contracts\ActionContext;
use Musing\InertiaTable\Jobs\ExecuteQueuedAction;
use Musing\InertiaTable\Selection;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;
use Throwable;

final class QueuedActionDispatcher
{
    /** @return array<string, mixed> */
    public function dispatch(
        Request $request,
        Table $table,
        Action $action,
        Selection $selection,
        string $idempotencyKey,
    ): array {
        $context = app($action->contextClass());

        if (! $context instanceof ActionContext) {
            throw new LogicException('The queued action context is invalid.');
        }

        $actorId = $context->actorId($request, $table, $action);
        $attributes = $action->resolvedScopeAttributes($request, $table);
        $configuration = $action->queueConfiguration();
        $normalizedSelection = $selection->toArray();
        $definitionFingerprint = $action->definitionFingerprint();
        $idempotencyFingerprint = hash('sha256', json_encode([
            'table' => $table::class,
            'name' => $table->name(),
            'action' => $action->key,
            'definition' => $definitionFingerprint,
            'actor' => $actorId,
            'attributes' => $attributes,
            'selection' => $normalizedSelection,
            'key' => $idempotencyKey,
        ], JSON_THROW_ON_ERROR));
        $repository = app(QueuedActionRepository::class);
        $id = (string) Str::uuid();
        $ttl = $configuration['expiresAfter'] + $configuration['statusRetention'];
        $now = time();
        $existingId = $repository->reserve($idempotencyFingerprint, $id, $ttl);

        if ($existingId !== null) {
            $status = $repository->get($existingId) ?? $this->initialStatus(
                $request,
                $table,
                $action,
                $selection,
                $existingId,
                $now + $configuration['expiresAfter'],
                $ttl,
                $actorId,
                $attributes,
                $configuration['statusRetention'],
                $repository,
            );
            $repository->putIfMissing($existingId, $status, $ttl);

            return $repository->forResponse([
                ...$status,
                'duplicate' => true,
            ]);
        }

        $snapshot = new QueuedActionSnapshot(
            id: $id,
            tableClass: $table::class,
            tableName: $table->name(),
            actionKey: $action->key,
            definitionFingerprint: $definitionFingerprint,
            selection: $normalizedSelection,
            actorId: $actorId,
            scopeAttributes: $attributes,
            contextClass: $action->contextClass(),
            locale: app()->getLocale(),
            dispatchedAt: $now,
            expiresAt: $now + $configuration['expiresAfter'],
            idempotencyFingerprint: $idempotencyFingerprint,
        );
        $status = $this->initialStatus(
            $request,
            $table,
            $action,
            $selection,
            $id,
            $snapshot->expiresAt,
            $ttl,
            $actorId,
            $attributes,
            $configuration['statusRetention'],
            $repository,
        );
        $repository->put($id, $status, $ttl);
        $job = new ExecuteQueuedAction(
            $snapshot,
            $configuration['statusRetention'],
            $action->resolvedTags($request, $table, $snapshot),
        );
        $job->onConnection($configuration['connection']);
        $job->onQueue($configuration['queue']);
        $job->delay($configuration['delay']);
        $configuration['afterCommit'] ? $job->afterCommit() : $job->beforeCommit();
        $job->through($action->resolvedMiddleware($request, $table, $snapshot));
        $job->chain($action->resolvedChain($request, $table, $snapshot));

        try {
            dispatch($job);
        } catch (Throwable $exception) {
            $repository->put($id, [
                ...$status,
                'status' => 'failed',
                'message' => $action->publicFailureMessage(),
                'failedAt' => time(),
            ], $ttl);

            throw $exception;
        }

        return $repository->forResponse($status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function initialStatus(
        Request $request,
        Table $table,
        Action $action,
        Selection $selection,
        string $id,
        int $expiresAt,
        int $signedUrlTtl,
        int|string|null $actorId,
        array $attributes,
        int $statusRetention,
        QueuedActionRepository $repository,
    ): array {
        $resolved = $action->resolve(request: $request);

        return [
            'id' => $id,
            'action' => $action->key,
            'label' => $resolved['label'],
            'status' => 'queued',
            'total' => $selection->count(),
            'processed' => $action->handlesSelection() ? null : 0,
            'succeeded' => $action->handlesSelection() ? null : 0,
            'skipped' => $action->handlesSelection() ? null : 0,
            'result' => null,
            'message' => null,
            'expiresAt' => $expiresAt,
            'statusEndpoint' => URL::temporarySignedRoute(
                'inertia-table.action-status',
                now()->addSeconds($signedUrlTtl),
                [
                    'table' => TableReference::encode($table::class),
                    'action' => $action->key,
                    'id' => $id,
                ],
                absolute: false,
            ),
            'redirect' => $action->resolvedDispatchRedirect($request, $table),
            'duplicate' => false,
            '_accessHash' => $repository->accessHash(
                $table::class,
                $action->key,
                $actorId,
                $attributes,
            ),
            '_statusRetention' => $statusRetention,
        ];
    }
}
